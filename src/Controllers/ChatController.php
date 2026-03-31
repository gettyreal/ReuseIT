<?php
namespace ReuseIT\Controllers;

use ReuseIT\Response;
use ReuseIT\Services\ChatService;
use InvalidArgumentException;
use Exception;

/**
 * ChatController
 *
 * Handles chat HTTP endpoints for messaging operations.
 * All endpoints are protected by AuthMiddleware (require authentication).
 *
 * Protected endpoints:
 * - GET /api/conversations - List user's conversations with unread counts
 * - GET /api/conversations/:id/messages - Get conversation message history
 * - GET /api/conversations/:id/messages/new - Get delta messages since timestamp
 * - POST /api/conversations/:id/messages - Send a message
 * - PATCH /api/conversations/:id/mark-read - Bulk mark conversation as read
 * - PATCH /api/messages/:id/mark-read - Mark single message as read
 */
class ChatController
{
    private ChatService $chatService;

    /**
     * Initialize controller with service dependencies.
     *
     * @param ChatService $chatService Service layer for chat operations
     */
    public function __construct(ChatService $chatService)
    {
        $this->chatService = $chatService;
    }

    /**
     * GET /api/conversations
     *
     * List all conversations for authenticated user with pagination and unread counts.
     * Protected endpoint - requires authentication.
     *
     * Query parameters:
     * - limit: int (1-50, default 20) - conversations per page
     * - offset: int (0-N, default 0) - pagination offset
     *
     * Response format:
     * {
     *   "status": "success",
     *   "data": [
     *     {
     *       "id": 1,
     *       "booking_id": 14,
     *       "listing_title": "iPhone 15",
     *       "other_user_name": "Alice",
     *       "other_user_avatar": "...",
     *       "unread_count": 3,
     *       "last_message_at": "2026-03-30 15:30:00",
     *       "updated_at": "2026-03-30 15:30:00"
     *     }
     *   ]
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters
     * @return string JSON response
     */
    public function getConversations(array $get, array $post, array $files, array $params): string
    {
        try {
            // Check authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];

            // Parse pagination parameters
            $limit = isset($get['limit']) ? (int)$get['limit'] : 20;
            $offset = isset($get['offset']) ? (int)$get['offset'] : 0;

            // Validate limit is in range [1, 50]
            if ($limit < 1 || $limit > 50) {
                $limit = 20;
            }
            if ($offset < 0) {
                $offset = 0;
            }

            // Get conversations via service
            $conversations = $this->chatService->getUserConversations($userId, $limit, $offset);
            $formatted = [];

            foreach ($conversations as $conversation) {
                $formatted[] = [
                    'id' => (int)($conversation['id'] ?? 0),
                    'booking_id' => isset($conversation['booking_id']) ? (int)$conversation['booking_id'] : null,
                    'listing_id' => (int)($conversation['listing_id'] ?? 0),
                    'buyer_id' => (int)($conversation['buyer_id'] ?? 0),
                    'seller_id' => (int)($conversation['seller_id'] ?? 0),
                    'listing_title' => $conversation['listing_title'] ?? null,
                    'other_user_id' => isset($conversation['other_user_id']) ? (int)$conversation['other_user_id'] : null,
                    'other_user_first_name' => $conversation['other_user_first_name'] ?? null,
                    'other_user_last_name' => $conversation['other_user_last_name'] ?? null,
                    'other_user_avatar_url' => $conversation['other_user_avatar_url'] ?? null,
                    'unread' => isset($conversation['unread']) ? (bool)$conversation['unread'] : false,
                    'unread_count' => (int)($conversation['unread_count'] ?? 0),
                    'last_message_at' => $conversation['last_message_at'] ?? null,
                    'created_at' => $conversation['created_at'] ?? null,
                    'updated_at' => $conversation['updated_at'] ?? null,
                ];
            }

            return Response::success($formatted, 200);

        } catch (Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * GET /api/conversations/:id/messages
     *
     * Get conversation message history with pagination.
     * Protected endpoint - requires authentication and authorization.
     * Auto-marks all returned messages as read for the requesting user.
     *
     * URL parameters:
     * - id: int - conversation_id
     *
     * Query parameters:
     * - limit: int (1-50, default 20) - messages per page
     * - offset: int (0-N, default 0) - pagination offset
     *
     * Response includes X-Poll-Interval header (3000 milliseconds).
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function getConversationMessages(array $get, array $post, array $files, array $params): string
    {
        try {
            // Check authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $conversationId = (int)($params['id'] ?? 0);

            // Validate conversationId
            if ($conversationId <= 0) {
                return Response::error('Invalid conversation ID', 400);
            }

            // Parse pagination parameters
            $limit = isset($get['limit']) ? (int)$get['limit'] : 20;
            $offset = isset($get['offset']) ? (int)$get['offset'] : 0;

            // Validate limit is in range [1, 50]
            if ($limit < 1 || $limit > 50) {
                $limit = 20;
            }
            if ($offset < 0) {
                $offset = 0;
            }

            // Get conversation messages
            $result = $this->chatService->getConversationHistory($conversationId, $userId, $limit, $offset);

            // Add X-Poll-Interval header (3000 milliseconds = 3 seconds)
            header('X-Poll-Interval: 3000');

            return Response::success([
                'messages' => $result['messages'],
                'unread_count' => $result['unread_count'],
                'conversation_updated_at' => $result['conversation_updated_at']
            ], 200);

        } catch (InvalidArgumentException $e) {
            // Unauthorized (not participant)
            return Response::error('Unauthorized', 403);
        } catch (Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * GET /api/conversations/:id/messages/new
     *
     * Get delta messages since timestamp (for polling).
     * Protected endpoint - requires authentication and authorization.
     * Auto-marks all returned messages as read for the requesting user.
     *
     * URL parameters:
     * - id: int - conversation_id
     *
     * Query parameters:
     * - since: string (REQUIRED) - timestamp in 'Y-m-d H:i:s' or ISO 8601 format
     *
     * Response includes X-Poll-Interval header (3000 milliseconds).
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function getNewMessages(array $get, array $post, array $files, array $params): string
    {
        try {
            // Check authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $conversationId = (int)($params['id'] ?? 0);

            // Validate conversationId
            if ($conversationId <= 0) {
                return Response::error('Invalid conversation ID', 400);
            }

            // Validate 'since' parameter is present and valid
            if (empty($get['since'])) {
                return Response::error('Parameter "since" is required', 400);
            }

            $since = (string)$get['since'];

            // Validate timestamp format (accept both 'Y-m-d H:i:s' and ISO 8601)
            // Try to parse as both formats
            $timestamp = null;
            if (strtotime($since) !== false) {
                // Convert to 'Y-m-d H:i:s' format
                $timestamp = date('Y-m-d H:i:s', strtotime($since));
            } else {
                return Response::error('Invalid timestamp format', 400);
            }

            // Get new messages since timestamp
            $result = $this->chatService->getNewMessages($conversationId, $userId, $timestamp);

            // Add X-Poll-Interval header (3000 milliseconds = 3 seconds)
            header('X-Poll-Interval: 3000');

            return Response::success([
                'messages' => $result['messages'],
                'unread_count' => $result['unread_count'],
                'conversation_updated_at' => $result['conversation_updated_at']
            ], 200);

        } catch (InvalidArgumentException $e) {
            // Unauthorized (not participant)
            return Response::error('Unauthorized', 403);
        } catch (Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * POST /api/conversations/:id/messages
     *
     * Send a message in a conversation.
     * Protected endpoint - requires authentication and authorization.
     *
     * URL parameters:
     * - id: int - conversation_id
     *
     * Request body (JSON):
     * {
     *   "content": "message text"
     * }
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function sendMessage(array $get, array $post, array $files, array $params): string
    {
        try {
            // Check authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $conversationId = (int)($params['id'] ?? 0);

            // Validate conversationId
            if ($conversationId <= 0) {
                return Response::error('Invalid conversation ID', 400);
            }

            // Parse JSON request body
            $input = json_decode(file_get_contents('php://input'), true) ?? [];

            // Validate content field
            if (empty($input['content']) || !is_string($input['content'])) {
                return Response::validationErrors([
                    ['field' => 'content', 'message' => 'Message content is required']
                ], 400);
            }

            $content = (string)$input['content'];

            // Send message via service
            $messageId = $this->chatService->sendMessage($conversationId, $userId, $content);

            return Response::success([
                'id' => $messageId['id'],
                'created_at' => $messageId['created_at']
            ], 201);

        } catch (InvalidArgumentException $e) {
            $message = $e->getMessage();
            // Check if it's a validation error or authorization error
            if (strpos($message, 'Not authorized') !== false) {
                return Response::error('Unauthorized', 403);
            }
            // Message validation error (from ChatService)
            return Response::validationErrors([
                ['field' => 'content', 'message' => $message]
            ], 400);
        } catch (Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * PATCH /api/conversations/:id/mark-read
     *
     * Bulk mark conversation as read for authenticated user.
     * Protected endpoint - requires authentication and authorization.
     *
     * URL parameters:
     * - id: int - conversation_id
     *
     * Body: empty or {}
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function markConversationRead(array $get, array $post, array $files, array $params): string
    {
        try {
            // Check authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $conversationId = (int)($params['id'] ?? 0);

            // Validate conversationId
            if ($conversationId <= 0) {
                return Response::error('Invalid conversation ID', 400);
            }

            // Fetch conversation history to trigger auto-mark (or call repository directly)
            // We'll fetch with limit=1 to get conversation info and trigger auto-mark
            $this->chatService->getConversationHistory($conversationId, $userId, 1, 0);

            return Response::success(['status' => 'marked_read'], 200);

        } catch (InvalidArgumentException $e) {
            // Unauthorized (not participant)
            return Response::error('Unauthorized', 403);
        } catch (Exception $e) {
            return Response::error('Server error', 500);
        }
    }

    /**
     * PATCH /api/messages/:id/mark-read
     *
     * Mark a single message as read.
     * Protected endpoint - requires authentication and authorization.
     *
     * URL parameters:
     * - id: int - message_id
     *
     * Body: empty or {}
     *
     * @param array $get Query parameters
     * @param array $post POST parameters
     * @param array $files Uploaded files
     * @param array $params URI parameters (contains 'id')
     * @return string JSON response
     */
    public function markMessageRead(array $get, array $post, array $files, array $params): string
    {
        try {
            // Check authentication
            if (empty($_SESSION['user_id'])) {
                return Response::error('Unauthorized', 401);
            }

            $userId = (int)$_SESSION['user_id'];
            $messageId = (int)($params['id'] ?? 0);

            // Validate messageId
            if ($messageId <= 0) {
                return Response::error('Invalid message ID', 400);
            }

            // Mark message as read via service
            $this->chatService->markMessageRead($messageId, $userId);

            return Response::success(['status' => 'marked_read'], 200);

        } catch (InvalidArgumentException $e) {
            // Unauthorized (not participant)
            return Response::error('Unauthorized', 403);
        } catch (Exception $e) {
            return Response::error('Server error', 500);
        }
    }
}
