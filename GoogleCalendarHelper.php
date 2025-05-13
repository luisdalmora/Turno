<?php
// GoogleCalendarHelper.php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config.php'; // Para GOOGLE_APPLICATION_NAME, PATH_TO_CLIENT_SECRET_JSON, etc.
// A classe LogHelper deve ser definida em outro lugar e injetada.

use Google\Client as GoogleClient;
use Google\Service\Calendar as GoogleServiceCalendar;
use Google\Service\Calendar\Event as GoogleCalendarEvent;
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
        $this->client->setAuthConfig(PATH_TO_CLIENT_SECRET_JSON); // Carrega client_id, client_secret, etc.
        $this->client->setRedirectUri(GOOGLE_REDIRECT_URI);
        $this->client->setAccessType('offline'); // Necessário para obter refresh_token
        $this->client->setPrompt('select_account consent'); // Força o consentimento e a seleção da conta
        $this->client->setScopes([
            GoogleServiceCalendar::CALENDAR_READONLY,
            GoogleServiceCalendar::CALENDAR_EVENTS
        ]);
    }

    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }

    public function exchangeCodeForToken($authCode) {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            // A linha abaixo não é estritamente necessária aqui se $accessToken não for usado imediatamente pelo $this->client
            // mas é uma boa prática se outras operações do cliente se seguirem imediatamente.
            // $this->client->setAccessToken($accessToken);

            if (isset($accessToken['error'])) {
                $this->logger->log('GCAL_ERROR', 'Erro ao trocar código por token (resposta do Google).', [
                    'error_details' => $accessToken,
                    'auth_code_prefix' => substr($authCode, 0, 20) // Logar apenas parte do código por segurança
                ]);
                return null;
            }
            
            // O token é válido, vamos defini-lo no cliente para uso futuro e salvá-lo
            $this->client->setAccessToken($accessToken);

            if (isset($_SESSION['usuario_id'])) {
                $this->saveTokenForUser($_SESSION['usuario_id'], $accessToken);
                $this->logger->log('GCAL_INFO', 'Token do Google salvo para o usuário.', ['user_id' => $_SESSION['usuario_id']]);
            } else {
                $this->logger->log('GCAL_WARNING', 'Não foi possível salvar o token: usuário não logado na sessão.');
            }
            return $accessToken;

        } catch (GoogleServiceException $e) {
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao trocar código: ' . $e->getMessage(), [
                'errors' => $e->getErrors(), 
                'trace' => $e->getTraceAsString(),
                'auth_code_prefix' => substr($authCode, 0, 20)
            ]);
            return null;
        } catch (GuzzleRequestException $e) {
            $this->logger->log('GCAL_ERROR', 'Guzzle HTTP Exception ao trocar código: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'auth_code_prefix' => substr($authCode, 0, 20)
            ]);
            if ($e->hasResponse()) {
                $this->logger->log('GCAL_ERROR_RESPONSE', 'Resposta do Guzzle (troca de código): ' . (string) $e->getResponse()->getBody());
            }
            return null;
        } catch (Exception $e) { // Generic fallback
            $this->logger->log('GCAL_ERROR', 'Exceção genérica ao trocar código por token: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'auth_code_prefix' => substr($authCode, 0, 20)
            ]);
            return null;
        }
    }

    private function saveTokenForUser($userId, $tokenData) {
        if (!$this->conexao_db) {
            $_SESSION['google_access_token_user_' . $userId] = $tokenData;
            $this->logger->log('GCAL_INFO', 'Token do Google salvo na SESSÃO para user_id: ' . $userId . '. Recomenda-se BD para produção.');
            return;
        }

        $accessToken = $tokenData['access_token'];
        $refreshToken = isset($tokenData['refresh_token']) ? $tokenData['refresh_token'] : null;
        // expires_in é o número de segundos até a expiração.
        $expiresIn = isset($tokenData['expires_in']) ? (int) $tokenData['expires_in'] : 3599; // Default para ~1 hora se não vier
        // 'created' é o timestamp Unix de quando o token foi criado.
        $createdAt = isset($tokenData['created']) ? (int) $tokenData['created'] : time();

        // Lembre-se: a tabela google_user_tokens deve ter user_id como PRIMARY KEY ou UNIQUE para REPLACE INTO funcionar corretamente.
        // Schema sugerido para google_user_tokens:
        // user_id INT PRIMARY KEY
        // access_token TEXT NOT NULL
        // refresh_token TEXT NULL
        // expires_in INT NOT NULL
        // created_at INT NOT NULL 
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
        $source = 'session'; // Para log

        if (!$this->conexao_db) {
            if (isset($_SESSION['google_access_token_user_' . $userId])) {
                $tokenFromSource = $_SESSION['google_access_token_user_' . $userId];
            }
        } else {
            $source = 'database';
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
                        'created' => (int) $tokenDataDb['created_at'] // 'created' é o campo esperado pelo Google_Client
                    ];
                }
            } else {
                $this->logger->log('GCAL_ERROR', 'Falha ao preparar statement para buscar token do Google no BD.', ['user_id' => $userId, 'error' => mysqli_error($this->conexao_db)]);
                return null;
            }
        }

        if (!$tokenFromSource) {
            $this->logger->log('GCAL_INFO', 'Nenhum token encontrado para user_id: ' . $userId . ' (source: ' . $source . ')');
            return null;
        }

        $this->client->setAccessToken($tokenFromSource);

        if ($this->client->isAccessTokenExpired()) {
            $this->logger->log('GCAL_INFO', 'Token expirado para user_id: ' . $userId . ' (source: ' . $source . '). Tentando refresh.');
            $refreshToken = $this->client->getRefreshToken();
            if ($refreshToken) {
                try {
                    $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
                    // $newToken pode não incluir o 'refresh_token'. Devemos mesclar para preservá-lo.
                    $updatedTokenData = array_merge($tokenFromSource, $newToken);
                    // Garante que o refresh_token original seja mantido se o novo não o trouxer
                    if (!isset($newToken['refresh_token']) && isset($tokenFromSource['refresh_token'])) {
                        $updatedTokenData['refresh_token'] = $tokenFromSource['refresh_token'];
                    }
                    // Atualiza o 'created' para o momento do refresh, pois 'expires_in' é relativo a este.
                    $updatedTokenData['created'] = isset($newToken['created']) ? (int)$newToken['created'] : time();


                    $this->client->setAccessToken($updatedTokenData);
                    
                    // Salvar o token atualizado
                    if ($source === 'session') {
                        $_SESSION['google_access_token_user_' . $userId] = $updatedTokenData;
                    } else { // database
                        $this->saveTokenForUser($userId, $updatedTokenData);
                    }
                    $this->logger->log('GCAL_INFO', 'Token do Google (' . $source . ') atualizado via refresh token.', ['user_id' => $userId]);
                    return $this->client->getAccessToken(); // Retorna o array completo do token atualizado
                
                } catch (GoogleServiceException $e) {
                    $this->logger->log('GCAL_ERROR', 'Google Service Exception ao atualizar token (' . $source . ') via refresh: ' . $e->getMessage(), ['user_id' => $userId, 'errors' => $e->getErrors(), 'trace' => $e->getTraceAsString()]);
                } catch (GuzzleRequestException $e) {
                    $this->logger->log('GCAL_ERROR', 'Guzzle HTTP Exception ao atualizar token (' . $source . ') via refresh: ' . $e->getMessage(), ['user_id' => $userId, 'trace' => $e->getTraceAsString()]);
                    if ($e->hasResponse()) {
                        $this->logger->log('GCAL_ERROR_RESPONSE', 'Resposta do Guzzle (refresh ' . $source . '): ' . (string) $e->getResponse()->getBody());
                    }
                } catch (Exception $e) {
                    $this->logger->log('GCAL_ERROR', 'Erro genérico ao atualizar token (' . $source . ') via refresh: ' . $e->getMessage(), ['user_id' => $userId, 'trace' => $e->getTraceAsString()]);
                }
                // Se o refresh falhou
                if ($source === 'session') unset($_SESSION['google_access_token_user_' . $userId]);
                // Para BD, poderíamos deletar o token inválido ou marcar como tal
                $this->logger->log('GCAL_WARNING', 'Falha ao atualizar token via refresh para user_id: ' . $userId . '. Token removido/invalidado.');
                return null;
            } else {
                $this->logger->log('GCAL_WARNING', 'Token (' . $source . ') expirado e sem refresh token para user_id: ' . $userId . '. Reautenticação necessária.');
                if ($source === 'session') unset($_SESSION['google_access_token_user_' . $userId]);
                return null;
            }
        }
        return $this->client->getAccessToken(); // Retorna o array completo do token (pode ser o original se não expirado)
    }

    public function createEvent($userId, $summary, $description, $startDateTime, $endDateTime, $timeZone = 'America/Sao_Paulo', $attendees = []) {
        $tokenDataArray = $this->getAccessTokenForUser($userId);

        if (!$tokenDataArray || !isset($tokenDataArray['access_token'])) {
            $this->logger->log('GCAL_ERROR', 'Token inválido ou não obtido para criar evento. Reautenticação pode ser necessária.', ['user_id' => $userId]);
            return null;
        }
        // $this->client já foi configurado com o token por getAccessTokenForUser
        
        // Checagem adicional, embora getAccessTokenForUser deva garantir um token válido
        if ($this->client->isAccessTokenExpired()) {
             $this->logger->log('GCAL_ERROR', 'Token expirado mesmo após tentativa de refresh em getAccessTokenForUser. Não foi possível criar evento.', ['user_id' => $userId]);
             return null;
        }

        $service = new GoogleServiceCalendar($this->client);
        $event = new GoogleCalendarEvent([
            'summary' => $summary,
            'description' => $description,
            'start' => [
                'dateTime' => $startDateTime, // Formato RFC3339: '2025-05-20T09:00:00-03:00'
                'timeZone' => $timeZone,
            ],
            'end' => [
                'dateTime' => $endDateTime,   // Formato RFC3339: '2025-05-20T17:00:00-03:00'
                'timeZone' => $timeZone,
            ],
            'attendees' => $attendees, // Array de ['email' => 'email@example.com']
        ]);

        $calendarId = 'primary'; // Agenda principal do usuário autenticado
        try {
            $createdEvent = $service->events->insert($calendarId, $event);
            $this->logger->log('GCAL_SUCCESS', 'Evento criado no Google Calendar.', ['eventId' => $createdEvent->getId(), 'summary' => $summary, 'user_id' => $userId]);
            return $createdEvent->getId(); // Retorna o ID do evento criado
        } catch (GoogleServiceException $e) {
            $this->logger->log('GCAL_ERROR', 'Google Service Exception ao criar evento: ' . $e->getMessage(), [
                'summary' => $summary, 
                'user_id' => $userId, 
                'errors' => $e->getErrors(), 
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        } catch (Exception $e) {
            $this->logger->log('GCAL_ERROR', 'Erro genérico ao criar evento no Google Calendar: ' . $e->getMessage(), [
                'summary' => $summary, 
                'user_id' => $userId, 
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }
    
    public function revokeTokenForUser($userId) {
        // getAccessTokenForUser também configura $this->client com o token, se encontrado.
        $tokenDataArray = $this->getAccessTokenForUser($userId); 

        if ($tokenDataArray && isset($tokenDataArray['access_token'])) {
            // $this->client->setAccessToken($tokenDataArray); // Já feito por getAccessTokenForUser
            $tokenToRevoke = $this->client->getAccessToken(); // Pega o token completo (pode ter sido refrescado)

            // O Google recomenda revogar o refresh_token se ele existir, ou o access_token caso contrário.
            // A biblioteca revokeToken() lida com isso: se o token for um array, ele busca 'refresh_token' ou 'access_token'.
            try {
                $this->client->revokeToken($tokenToRevoke); 
                $this->logger->log('GCAL_INFO', 'Token revogado no Google para user_id: ' . $userId);
            } catch (GoogleServiceException $e) {
                $this->logger->log('GCAL_ERROR', 'Google Service Exception ao tentar revogar token: ' . $e->getMessage(), ['user_id' => $userId, 'errors' => $e->getErrors(), 'trace' => $e->getTraceAsString()]);
            } catch (GuzzleRequestException $e) {
                $this->logger->log('GCAL_ERROR', 'Guzzle HTTP Exception ao tentar revogar token: ' . $e->getMessage(), ['user_id' => $userId, 'trace' => $e->getTraceAsString()]);
                if ($e->hasResponse()) {
                    $this->logger->log('GCAL_ERROR_RESPONSE', 'Resposta do Guzzle (revoke): ' . (string) $e->getResponse()->getBody());
                }
            } catch (Exception $e) {
                $this->logger->log('GCAL_ERROR', 'Erro genérico ao tentar revogar token no Google: ' . $e->getMessage(), ['user_id' => $userId, 'trace' => $e->getTraceAsString()]);
            }
        } else {
            $this->logger->log('GCAL_INFO', 'Nenhum token ativo encontrado para revogar para user_id: ' . $userId);
        }

        // Remover do banco de dados, independentemente do sucesso da revogação no Google
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
        unset($_SESSION['google_access_token_user_' . $userId]);
        $this->logger->log('GCAL_INFO', 'Tentativa de remoção de token da sessão para user_id: ' . $userId . ' (se existia).');
    }
}