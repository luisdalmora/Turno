<?php
// GoogleCalendarHelper.php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php'; // Para GOOGLE_APPLICATION_NAME, PATH_TO_CLIENT_SECRET_JSON, etc.
// A classe LogHelper é injetada via construtor.

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleServiceCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent; // Usado para criar eventos
use Google\Service\Calendar\EventDateTime; // Usado para definir datas/horas de eventos
use Google\Service\Exception as GoogleServiceException;
use GuzzleHttp\Exception\RequestException as GuzzleRequestException;

class GoogleCalendarHelper {
    private $client;
    private $logger; // Instância de LogHelper
    private $conexao_db; // Conexão mysqli

    public function __construct(LogHelper $logger, $db_connection = null) {
        $this->logger = $logger;
        $this->conexao_db = $db_connection;

        $this->client = new GoogleClient();
        $this->client->setApplicationName(GOOGLE_APPLICATION_NAME);
        try {
            $this->client->setAuthConfig(PATH_TO_CLIENT_SECRET_JSON); // Carrega client_id, client_secret, etc.
        } catch (\Google\Exception $e) {
            $this->logger->log('GCAL_CRITICAL', 'Falha ao carregar arquivo de configuração JSON do Google: ' . $e->getMessage(), ['path' => PATH_TO_CLIENT_SECRET_JSON]);
            // Você pode querer lançar a exceção ou tratar de forma mais robusta
            // throw $e; 
        }
        $this->client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $this->client->setAccessType('offline'); // Necessário para obter refresh_token
        $this->client->setPrompt('select_account consent'); // Força o consentimento e a seleção da conta
        $this->client->setScopes([
            GoogleServiceCalendar::CALENDAR_EVENTS // Permissão para ler, criar, modificar e deletar eventos
            // GoogleServiceCalendar::CALENDAR_READONLY // Se você só precisasse ler, mas precisamos de CALENDAR_EVENTS
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
                $this->logger->log('GCAL_INFO', 'Token do Google salvo para o usuário.', ['user_id' => $_SESSION['usuario_id']]);
            } else {
                $this->logger->log('GCAL_WARNING', 'Não foi possível salvar o token: usuário não logado na sessão.');
            }
            return $accessToken;

        } catch (GoogleServiceException $e) {
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao trocar código: ' . $e->getMessage(), ['errors' => $e->getErrors(), 'auth_code_prefix' => substr($authCode, 0, 20)]);
            return null;
        } catch (GuzzleRequestException $e) { // Erros de HTTP Guzzle
            $this->logger->log('GCAL_ERROR', 'Guzzle HTTP Exception ao trocar código: ' . $e->getMessage(), ['auth_code_prefix' => substr($authCode, 0, 20)]);
            if ($e->hasResponse()) {
                $this->logger->log('GCAL_ERROR_RESPONSE', 'Resposta do Guzzle (troca de código): ' . (string) $e->getResponse()->getBody());
            }
            return null;
        } catch (Exception $e) { // Captura genérica
            $this->logger->log('GCAL_ERROR', 'Exceção genérica ao trocar código por token: ' . $e->getMessage(), ['auth_code_prefix' => substr($authCode, 0, 20)]);
            return null;
        }
    }

    private function saveTokenForUser($userId, $tokenData) {
        if (!$this->conexao_db) {
            // Fallback para sessão se não houver conexão com BD (não recomendado para produção)
            $_SESSION['google_access_token_user_' . $userId] = $tokenData;
            $this->logger->log('GCAL_INFO', 'Token do Google salvo na SESSÃO para user_id: ' . $userId . '. BD é recomendado.');
            return;
        }

        $accessToken = $tokenData['access_token'];
        $refreshToken = $tokenData['refresh_token'] ?? null; // Refresh token pode não vir em todos os fluxos de refresh
        $expiresIn = $tokenData['expires_in'] ?? 3599; 
        $createdAt = $tokenData['created'] ?? time();

        $sql = "REPLACE INTO google_user_tokens (user_id, access_token, refresh_token, expires_in, created_at) VALUES (?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($this->conexao_db, $sql);
        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "issii", $userId, $accessToken, $refreshToken, $expiresIn, $createdAt);
            if (!mysqli_stmt_execute($stmt)) {
                $this->logger->log('GCAL_ERROR', 'Falha ao salvar token do Google no BD.', ['user_id' => $userId, 'error' => mysqli_stmt_error($stmt)]);
            }
            mysqli_stmt_close($stmt);
        } else {
            $this->logger->log('GCAL_ERROR', 'Falha ao preparar statement para salvar token do Google no BD.', ['user_id' => $userId, 'error' => mysqli_error($this->conexao_db)]);
        }
    }

    public function getAccessTokenForUser($userId) {
        $tokenFromSource = null;
        $source_log_msg = 'session'; // Para log

        if ($this->conexao_db) {
            $source_log_msg = 'database';
            $sql = "SELECT access_token, refresh_token, expires_in, created_at FROM google_user_tokens WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conexao_db, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $userId);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $tokenDataDb = mysqli_fetch_assoc($result);
                mysqli_stmt_close($stmt);

                if ($tokenDataDb) {
                    $tokenFromSource = [
                        'access_token' => $tokenDataDb['access_token'],
                        'refresh_token' => $tokenDataDb['refresh_token'],
                        'expires_in' => (int) $tokenDataDb['expires_in'],
                        'created' => (int) $tokenDataDb['created_at'] 
                    ];
                }
            } else {
                $this->logger->log('GCAL_ERROR', 'Falha ao preparar statement para buscar token do Google no BD.', ['user_id' => $userId, 'error' => mysqli_error($this->conexao_db)]);
                return null;
            }
        } elseif (isset($_SESSION['google_access_token_user_' . $userId])) { // Fallback para sessão
            $tokenFromSource = $_SESSION['google_access_token_user_' . $userId];
        }


        if (!$tokenFromSource) {
            $this->logger->log('GCAL_INFO', 'Nenhum token encontrado (' . $source_log_msg . ') para user_id: ' . $userId);
            return null;
        }

        $this->client->setAccessToken($tokenFromSource);

        if ($this->client->isAccessTokenExpired()) {
            $this->logger->log('GCAL_INFO', 'Token (' . $source_log_msg . ') expirado para user_id: ' . $userId . '. Tentando refresh.');
            $storedRefreshToken = $this->client->getRefreshToken(); // Pega o refresh_token do $tokenFromSource

            if ($storedRefreshToken) {
                try {
                    // Busca um novo token de acesso usando o refresh token
                    $this->client->fetchAccessTokenWithRefreshToken($storedRefreshToken);
                    $newAccessTokenArray = $this->client->getAccessToken(); // Pega o token atualizado (que agora inclui o novo access_token)

                    // O novo array pode não incluir o refresh_token original se ele não mudou.
                    // Vamos garantir que o refresh_token seja preservado.
                    $newAccessTokenArray['refresh_token'] = $storedRefreshToken;
                    
                    // 'created' deve ser atualizado para o momento do refresh
                    $newAccessTokenArray['created'] = $newAccessTokenArray['created'] ?? time();


                    // Salva o token atualizado (com o novo access_token e o refresh_token antigo)
                    $this->saveTokenForUser($userId, $newAccessTokenArray);
                    
                    $this->logger->log('GCAL_INFO', 'Token do Google (' . $source_log_msg . ') atualizado via refresh token.', ['user_id' => $userId]);
                    return $this->client->getAccessToken(); // Retorna o array completo do token atualizado
                
                } catch (GoogleServiceException $e) {
                    $this->logger->log('GCAL_ERROR', 'Google Service Exception ao ATUALIZAR token via refresh (' . $source_log_msg . '): ' . $e->getMessage(), ['user_id' => $userId, 'errors' => $e->getErrors()]);
                } catch (GuzzleRequestException $e) {
                    $this->logger->log('GCAL_ERROR', 'Guzzle Exception ao ATUALIZAR token via refresh (' . $source_log_msg . '): ' . $e->getMessage(), ['user_id' => $userId]);
                } catch (Exception $e) {
                    $this->logger->log('GCAL_ERROR', 'Erro genérico ao ATUALIZAR token via refresh (' . $source_log_msg . '): ' . $e->getMessage(), ['user_id' => $userId]);
                }
                // Se o refresh falhou, remove o token inválido e retorna null
                $this->revokeTokenForUser($userId); // Revoga e remove do DB/sessão para forçar nova autenticação
                $this->logger->log('GCAL_WARNING', 'Falha ao atualizar token via refresh para user_id: ' . $userId . '. Token revogado. Reautenticação necessária.');
                return null;
            } else {
                $this->logger->log('GCAL_WARNING', 'Token (' . $source_log_msg . ') expirado e SEM refresh token para user_id: ' . $userId . '. Reautenticação necessária.');
                if ($this->conexao_db) $this->revokeTokenForUser($userId); // Remove do DB
                else unset($_SESSION['google_access_token_user_' . $userId]); // Remove da sessão
                return null;
            }
        }
        return $this->client->getAccessToken(); 
    }

    public function createEvent($userId, $summary, $description, $startDateTimeRfc3339, $endDateTimeRfc3339, $timeZone = 'America/Sao_Paulo', $attendees = []) {
        $tokenDataArray = $this->getAccessTokenForUser($userId);

        if (!$tokenDataArray || !isset($tokenDataArray['access_token'])) {
            $this->logger->log('GCAL_ERROR', 'Token inválido ou não obtido para CRIAR evento. Reautenticação pode ser necessária.', ['user_id' => $userId]);
            return null; 
        }
        // $this->client já foi configurado com o token por getAccessTokenForUser
        
        if ($this->client->isAccessTokenExpired()) { // Verificação extra
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
            $event->setAttendees($attendees); // Array de ['email' => 'email@example.com']
        }

        $calendarId = 'primary'; // Agenda principal do usuário autenticado
        try {
            $createdEvent = $service->events->insert($calendarId, $event);
            $this->logger->log('GCAL_SUCCESS', 'Evento CRIADO no Google Calendar.', ['eventId' => $createdEvent->getId(), 'summary' => $summary, 'user_id' => $userId]);
            return $createdEvent->getId(); // Retorna o ID do evento criado
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
            $errors = $e->getErrors(); // Array de erros
            $errorCode = $e->getCode(); // Código HTTP do erro

            // Se o evento não foi encontrado (404) ou já foi deletado (410 Gone), considera como "sucesso" para o nosso sistema, pois o objetivo é que ele não exista mais.
            if ($errorCode == 404 || $errorCode == 410) {
                $this->logger->log('GCAL_INFO', 'Evento não encontrado no Google Calendar ao tentar deletar (código ' . $errorCode . '), possivelmente já removido ou ID incorreto.', ['eventId' => $eventId, 'user_id' => $userId]);
                return true; 
            }
            
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao DELETAR evento: ' . $errorMessage, [
                'event_id' => $eventId, 
                'user_id' => $userId, 
                'google_errors' => $errors, 
                'google_error_code' => $errorCode
            ]);
            return false;
        } catch (Exception $e) { 
            $this->logger->log('GCAL_ERROR', 'Erro genérico ao DELETAR evento no Google Calendar: ' . $e->getMessage(), [
                'event_id' => $eventId, 
                'user_id' => $userId, 
                'trace' => $e->getTraceAsString() // Útil para depurar exceções inesperadas
            ]);
            return false;
        }
    }

    public function revokeTokenForUser($userId) {
        $tokenDataArray = $this->getAccessTokenForUser($userId); // Tenta pegar o token atual para revogar

        if ($tokenDataArray && isset($tokenDataArray['access_token'])) {
            // $this->client->setAccessToken($tokenDataArray); // Já feito por getAccessTokenForUser se encontrou e não expirou
            $tokenToRevoke = $this->client->getAccessToken(); // Pega o token completo (pode ter sido refrescado)
                                                            // Ou o token original se não expirou.
            
            // A biblioteca revokeToken() tenta usar o refresh_token se disponível, senão o access_token.
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
            $this->logger->log('GCAL_INFO', 'Nenhum token ativo encontrado para revogar (ou já revogado) para user_id: ' . $userId);
        }

        // Remover do banco de dados, independentemente do sucesso da revogação no Google (para garantir que o sistema não tente usá-lo)
        if ($this->conexao_db) {
            $sql = "DELETE FROM google_user_tokens WHERE user_id = ?";
            $stmt = mysqli_prepare($this->conexao_db, $sql);
            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $userId);
                if (mysqli_stmt_execute($stmt)) {
                    $this->logger->log('GCAL_INFO', 'Token removido do BD para user_id: ' . $userId);
                } else {
                    $this->logger->log('GCAL_ERROR', 'Falha ao remover token do BD para user_id: ' . $userId, ['error' => mysqli_stmt_error($stmt)]);
                }
                mysqli_stmt_close($stmt);
            } else {
                 $this->logger->log('GCAL_ERROR', 'Falha ao preparar statement para remover token do BD.', ['user_id' => $userId, 'error' => mysqli_error($this->conexao_db)]);
            }
        }
        // Remover da sessão (fallback ou se usava sessão)
        if (isset($_SESSION['google_access_token_user_' . $userId])) {
            unset($_SESSION['google_access_token_user_' . $userId]);
            $this->logger->log('GCAL_INFO', 'Token removido da SESSÃO para user_id: ' . $userId . ' (se existia).');
        }
    }

} // Fim da classe GoogleCalendarHelper
