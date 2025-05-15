<?php
// GoogleCalendarHelper.php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php'; 

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleServiceCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;

class GoogleCalendarHelper {
    private $client;
    private $logger; 
    private $conexao_db; // Conexão SQLSRV

    public function __construct(LogHelper $logger, $db_connection = null) {
        $this->logger = $logger;
        $this->conexao_db = $db_connection; // Recebe a conexão SQLSRV

        $this->client = new GoogleClient();
        $this->client->setApplicationName(GOOGLE_APPLICATION_NAME);
        try {
            $this->client->setAuthConfig(PATH_TO_CLIENT_SECRET_JSON);
        } catch (\Google\Exception $e) {
            $this->logger->log('GCAL_CRITICAL', 'Falha ao carregar arquivo de configuração JSON do Google: ' . $e->getMessage(), ['path' => PATH_TO_CLIENT_SECRET_JSON]);
            // throw $e; // Comentar ou tratar se não quiser que a aplicação pare
        }
        $this->client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        $this->client->setScopes([
            GoogleServiceCalendar::CALENDAR_EVENTS
        ]);
    }

    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }

    public function exchangeCodeForToken($authCode) {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);

            if (isset($accessToken['error'])) {
                $this->logger->log('GCAL_ERROR', 'Erro ao trocar código por token (resposta do Google).', [
                    'error_details' => $accessToken,
                    'auth_code_prefix' => substr($authCode, 0, 20)
                ]);
                return null;
            }

            $this->client->setAccessToken($accessToken);

            if (isset($_SESSION['usuario_id'])) {
                $this->saveTokenForUser($_SESSION['usuario_id'], $accessToken);
                // Log já acontece dentro de saveTokenForUser
            } else {
                $this->logger->log('GCAL_WARNING', 'Não foi possível salvar o token: usuário não logado na sessão.');
            }
            return $accessToken;

        } catch (GoogleServiceException $e) {
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao trocar código: ' . $e->getMessage(), ['errors' => $e->getErrors(), 'auth_code_prefix' => substr($authCode, 0, 20)]);
            return null;
        } catch (GuzzleRequestException $e) {
            $this->logger->log('GCAL_ERROR', 'Guzzle HTTP Exception ao trocar código: ' . $e->getMessage(), ['auth_code_prefix' => substr($authCode, 0, 20)]);
            if ($e->hasResponse()) {
                $this->logger->log('GCAL_ERROR_RESPONSE', 'Resposta do Guzzle (troca de código): ' . (string) $e->getResponse()->getBody());
            }
            return null;
        } catch (Exception $e) {
            $this->logger->log('GCAL_ERROR', 'Exceção genérica ao trocar código por token: ' . $e->getMessage(), ['auth_code_prefix' => substr($authCode, 0, 20)]);
            return null;
        }
    }

    private function saveTokenForUser($userId, $tokenData) {
        if (!$this->conexao_db) {
            $_SESSION['google_access_token_user_' . $userId] = $tokenData;
            $this->logger->log('GCAL_INFO', 'Token do Google salvo na SESSÃO para user_id: ' . $userId . '. BD é recomendado.');
            return;
        }

        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null;
        $expiresIn = $tokenData['expires_in'] ?? 3599;
        $createdAt = $tokenData['created'] ?? time();

        // Para SQL Server, REPLACE INTO não existe. Usaremos MERGE.
        // Assumindo que a tabela google_user_tokens tem uma chave primária ou única em user_id.
        $sql = "
            MERGE google_user_tokens AS Target
            USING (VALUES (?, ?, ?, ?, ?)) AS Source (user_id_s, access_token_s, refresh_token_s, expires_in_s, created_at_s)
            ON Target.user_id = Source.user_id_s
            WHEN MATCHED THEN
                UPDATE SET 
                    Target.access_token = Source.access_token_s,
                    Target.refresh_token = Source.refresh_token_s,
                    Target.expires_in = Source.expires_in_s,
                    Target.created_at = Source.created_at_s
            WHEN NOT MATCHED BY TARGET THEN
                INSERT (user_id, access_token, refresh_token, expires_in, created_at) 
                VALUES (Source.user_id_s, Source.access_token_s, Source.refresh_token_s, Source.expires_in_s, Source.created_at_s);
        ";
        
        $params = array($userId, $accessToken, $refreshToken, $expiresIn, $createdAt);
        $stmt = sqlsrv_prepare($this->conexao_db, $sql, $params);

        if ($stmt) {
            if (!sqlsrv_execute($stmt)) {
                $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
                $this->logger->log('GCAL_ERROR', 'Falha ao salvar/atualizar token do Google no BD (SQLSRV).', ['user_id' => $userId, 'errors_sqlsrv' => $errors]);
            } else {
                 $this->logger->log('GCAL_INFO', 'Token do Google salvo/atualizado no BD para o usuário.', ['user_id' => $userId]);
            }
            sqlsrv_free_stmt($stmt);
        } else {
            $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
            $this->logger->log('GCAL_ERROR', 'Falha ao preparar statement MERGE para token do Google (SQLSRV).', ['user_id' => $userId, 'errors_sqlsrv' => $errors]);
        }
    }

    public function getAccessTokenForUser($userId) {
        $tokenFromSource = null;
        $source_log_msg = 'session'; // Para log

        if ($this->conexao_db) {
            $source_log_msg = 'database';
            $sql = "SELECT access_token, refresh_token, expires_in, created_at FROM google_user_tokens WHERE user_id = ?";
            $params = array($userId);
            $stmt = sqlsrv_query($this->conexao_db, $sql, $params); // Usando sqlsrv_query para SELECT simples

            if ($stmt) {
                $tokenDataDb = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                sqlsrv_free_stmt($stmt);

                if ($tokenDataDb) {
                    $tokenFromSource = [
                        'access_token' => $tokenDataDb['access_token'],
                        'refresh_token' => $tokenDataDb['refresh_token'],
                        'expires_in' => (int) $tokenDataDb['expires_in'],
                        'created' => (int) $tokenDataDb['created_at']
                    ];
                }
            } else {
                $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
                $this->logger->log('GCAL_ERROR', 'Falha ao executar query para buscar token do Google no BD (SQLSRV).', ['user_id' => $userId, 'errors_sqlsrv' => $errors]);
                return null;
            }
        } elseif (isset($_SESSION['google_access_token_user_' . $userId])) { 
            $tokenFromSource = $_SESSION['google_access_token_user_' . $userId];
        }

        if (!$tokenFromSource) {
            $this->logger->log('GCAL_INFO', 'Nenhum token encontrado (' . $source_log_msg . ') para user_id: ' . $userId);
            return null;
        }

        $this->client->setAccessToken($tokenFromSource);

        if ($this->client->isAccessTokenExpired()) {
            $this->logger->log('GCAL_INFO', 'Token (' . $source_log_msg . ') expirado para user_id: ' . $userId . '. Tentando refresh.');
            $storedRefreshToken = $this->client->getRefreshToken(); 

            if ($storedRefreshToken) {
                try {
                    $this->client->fetchAccessTokenWithRefreshToken($storedRefreshToken);
                    $newAccessTokenArray = $this->client->getAccessToken();
                    $newAccessTokenArray['refresh_token'] = $storedRefreshToken; 
                    $newAccessTokenArray['created'] = $newAccessTokenArray['created'] ?? time();
                    $this->saveTokenForUser($userId, $newAccessTokenArray);
                    $this->logger->log('GCAL_INFO', 'Token do Google (' . $source_log_msg . ') atualizado via refresh token.', ['user_id' => $userId]);
                    return $this->client->getAccessToken();
                } catch (GoogleServiceException $e) {
                    $this->logger->log('GCAL_ERROR', 'Google Service Exception ao ATUALIZAR token via refresh (' . $source_log_msg . '): ' . $e->getMessage(), ['user_id' => $userId, 'errors' => $e->getErrors()]);
                } catch (GuzzleRequestException $e) {
                    $this->logger->log('GCAL_ERROR', 'Guzzle Exception ao ATUALIZAR token via refresh (' . $source_log_msg . '): ' . $e->getMessage(), ['user_id' => $userId]);
                } catch (Exception $e) {
                    $this->logger->log('GCAL_ERROR', 'Erro genérico ao ATUALIZAR token via refresh (' . $source_log_msg . '): ' . $e->getMessage(), ['user_id' => $userId]);
                }
                $this->revokeTokenForUser($userId, false); // false para não tentar pegar token de novo dentro de revoke
                $this->logger->log('GCAL_WARNING', 'Falha ao atualizar token via refresh para user_id: ' . $userId . '. Token revogado localmente. Reautenticação necessária.');
                return null;
            } else {
                $this->logger->log('GCAL_WARNING', 'Token (' . $source_log_msg . ') expirado e SEM refresh token para user_id: ' . $userId . '. Reautenticação necessária.');
                if ($this->conexao_db) $this->removeTokenFromDb($userId);
                else unset($_SESSION['google_access_token_user_' . $userId]);
                return null;
            }
        }
        return $this->client->getAccessToken();
    }
    
    // Função auxiliar para remover do DB sem tentar revogar no Google novamente
    private function removeTokenFromDb($userId) {
        if ($this->conexao_db) {
            $sql = "DELETE FROM google_user_tokens WHERE user_id = ?";
            $params = array($userId);
            $stmt = sqlsrv_prepare($this->conexao_db, $sql, $params);
            if ($stmt) {
                if (sqlsrv_execute($stmt)) {
                    $this->logger->log('GCAL_INFO', 'Token removido do BD (interno) para user_id: ' . $userId);
                } else {
                    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
                    $this->logger->log('GCAL_ERROR', 'Falha ao remover token do BD (interno) para user_id: ' . $userId, ['errors_sqlsrv' => $errors]);
                }
                sqlsrv_free_stmt($stmt);
            } else {
                 $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
                 $this->logger->log('GCAL_ERROR', 'Falha ao preparar statement para remover token do BD (interno).', ['user_id' => $userId, 'errors_sqlsrv' => $errors]);
            }
        }
    }


    public function createEvent($userId, $summary, $description, $startDateTimeRfc3339, $endDateTimeRfc3339, $timeZone = 'America/Sao_Paulo', $attendees = []) {
        $tokenDataArray = $this->getAccessTokenForUser($userId);

        if (!$tokenDataArray || !isset($tokenDataArray['access_token'])) {
            $this->logger->log('GCAL_ERROR', 'Token inválido ou não obtido para CRIAR evento. Reautenticação pode ser necessária.', ['user_id' => $userId]);
            return null;
        }
        
        if ($this->client->isAccessTokenExpired()) { 
             $this->logger->log('GCAL_ERROR', 'Token expirado mesmo após tentativa de refresh. Não foi possível CRIAR evento.', ['user_id' => $userId]);
             return null;
        }

        $service = new GoogleServiceCalendar($this->client);
        $event = new GoogleCalendarEvent();
        $event->setSummary($summary);
        $event->setDescription($description);

        $start = new EventDateTime();
        $start->setDateTime($startDateTimeRfc3339);
        $start->setTimeZone($timeZone);
        $event->setStart($start);

        $end = new EventDateTime();
        $end->setDateTime($endDateTimeRfc3339);
        $end->setTimeZone($timeZone);
        $event->setEnd($end);

        if (!empty($attendees)) {
            $event->setAttendees($attendees);
        }

        $calendarId = 'primary';
        try {
            $createdEvent = $service->events->insert($calendarId, $event);
            $this->logger->log('GCAL_SUCCESS', 'Evento CRIADO no Google Calendar.', ['eventId' => $createdEvent->getId(), 'summary' => $summary, 'user_id' => $userId]);
            return $createdEvent->getId();
        } catch (GoogleServiceException $e) {
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao CRIAR evento: ' . $e->getMessage(), [
                'summary' => $summary, 'user_id' => $userId, 'errors' => $e->getErrors()
            ]);
            return null;
        } catch (Exception $e) {
            $this->logger->log('GCAL_ERROR', 'Erro genérico ao CRIAR evento no Google Calendar: ' . $e->getMessage(), [
                'summary' => $summary, 'user_id' => $userId
            ]);
            return null;
        }
    }

    public function deleteEvent($userId, $eventId) {
        if (empty($eventId)) {
            $this->logger->log('GCAL_INFO', 'Tentativa de deletar evento com ID vazio.', ['user_id' => $userId]);
            return false;
        }

        $tokenDataArray = $this->getAccessTokenForUser($userId);
        if (!$tokenDataArray || !isset($tokenDataArray['access_token'])) {
            $this->logger->log('GCAL_ERROR', 'Token inválido ou não obtido para DELETAR evento do Google Calendar.', ['user_id' => $userId, 'event_id' => $eventId]);
            return false;
        }

        if ($this->client->isAccessTokenExpired()) {
             $this->logger->log('GCAL_ERROR', 'Token expirado mesmo após refresh. Não foi possível DELETAR evento.', ['user_id' => $userId, 'event_id' => $eventId]);
             return false;
        }

        $service = new GoogleServiceCalendar($this->client);
        $calendarId = 'primary';

        try {
            $service->events->delete($calendarId, $eventId);
            $this->logger->log('GCAL_SUCCESS', 'Evento DELETADO do Google Calendar com sucesso.', ['eventId' => $eventId, 'user_id' => $userId]);
            return true;
        } catch (GoogleServiceException $e) {
            $errorMessage = $e->getMessage();
            $errors = $e->getErrors(); 
            $errorCode = $e->getCode(); 

            if ($errorCode == 404 || $errorCode == 410) {
                $this->logger->log('GCAL_INFO', 'Evento não encontrado no Google Calendar ao tentar deletar (código ' . $errorCode . '), possivelmente já removido ou ID incorreto.', ['eventId' => $eventId, 'user_id' => $userId]);
                return true;
            }

            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao DELETAR evento: ' . $errorMessage, [
                'event_id' => $eventId, 'user_id' => $userId, 'google_errors' => $errors, 'google_error_code' => $errorCode
            ]);
            return false;
        } catch (Exception $e) {
            $this->logger->log('GCAL_ERROR', 'Erro genérico ao DELETAR evento no Google Calendar: ' . $e->getMessage(), [
                'event_id' => $eventId, 'user_id' => $userId, 'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }
    
    public function listEventsFromCalendar($userId, $calendarId, $optParams = []) {
        $tokenDataArray = $this->getAccessTokenForUser($userId);

        if (!$tokenDataArray || !isset($tokenDataArray['access_token'])) {
            $this->logger->log('GCAL_ERROR', 'Token inválido ou não obtido para LISTAR eventos de ' . $calendarId, ['user_id' => $userId]);
            return null;
        }

        if ($this->client->isAccessTokenExpired()) {
             $this->logger->log('GCAL_ERROR', 'Token expirado mesmo após tentativa de refresh. Não foi possível LISTAR eventos.', ['user_id' => $userId, 'calendar_id' => $calendarId]);
             return null;
        }

        $service = new GoogleServiceCalendar($this->client);

        try {
            if (!$this->client instanceof \Google\Client) {
                $this->logger->log('GCAL_CRITICAL', 'Google Client não inicializado corretamente em listEventsFromCalendar.', ['user_id' => $userId]);
                return null;
            }
            $events = $service->events->listEvents($calendarId, $optParams);
            return $events->getItems();
        } catch (GoogleServiceException $e) { 
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'user_id' => $userId, 'calendar_id' => $calendarId, 'errors' => $e->getErrors()
            ]);
            if (is_array($e->getErrors()) && !empty($e->getErrors())) {
                foreach ($e->getErrors() as $error) {
                    if (isset($error['reason']) && ($error['reason'] == 'authError' || $error['reason'] == 'forbidden' || $error['reason'] == 'invalidCredentials')) {
                        $this->logger->log('GCAL_AUTH_FAILURE', 'Falha de autenticação/permissão ao listar eventos GCal.', ['user_id' => $userId, 'calendar_id' => $calendarId]);
                    }
                }
            }
            return null;
        } catch (\Google\Exception $e) { 
             $this->logger->log('GCAL_ERROR', 'Google Library Exception ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'user_id' => $userId, 'calendar_id' => $calendarId
            ]);
            return null;
        } catch (Exception $e) { 
            $this->logger->log('GCAL_ERROR', 'Erro genérico ao LISTAR eventos de ' . $calendarId . ': ' . $e->getMessage(), [
                'user_id' => $userId, 'calendar_id' => $calendarId, 'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    public function revokeTokenForUser($userId, $attemptGoogleRevoke = true) {
        if ($attemptGoogleRevoke) {
            $tokenDataArray = $this->getAccessTokenForUser($userId); 
            if ($tokenDataArray && isset($tokenDataArray['access_token'])) {
                $tokenToRevoke = $this->client->getAccessToken();
                try {
                    $this->client->revokeToken($tokenToRevoke);
                    $this->logger->log('GCAL_INFO', 'Token revogado no Google para user_id: ' . $userId);
                } catch (GoogleServiceException $e) {
                    $this->logger->log('GCAL_ERROR', 'Google Service Exception ao tentar revogar token: ' . $e->getMessage(), ['user_id' => $userId, 'errors' => $e->getErrors()]);
                } catch (GuzzleRequestException $e) {
                    $this->logger->log('GCAL_ERROR', 'Guzzle HTTP Exception ao tentar revogar token: ' . $e->getMessage(), ['user_id' => $userId]);
                    if ($e->hasResponse()) {
                        $this->logger->log('GCAL_ERROR_RESPONSE', 'Resposta do Guzzle (revoke): ' . (string) $e->getResponse()->getBody());
                    }
                } catch (Exception $e) {
                    $this->logger->log('GCAL_ERROR', 'Erro genérico ao tentar revogar token no Google: ' . $e->getMessage(), ['user_id' => $userId]);
                }
            } else {
                $this->logger->log('GCAL_INFO', 'Nenhum token ativo encontrado para revogar no Google (ou já revogado) para user_id: ' . $userId);
            }
        }
        
        $this->removeTokenFromDb($userId); // Chama a função auxiliar para remover do DB

        if (isset($_SESSION['google_access_token_user_' . $userId])) {
            unset($_SESSION['google_access_token_user_' . $userId]);
            $this->logger->log('GCAL_INFO', 'Token removido da SESSÃO para user_id: ' . $userId . ' (se existia).');
        }
    }
}
