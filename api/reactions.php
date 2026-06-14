<?php
/**
 * Reactions API
 * POST /api/reactions.php - Add reaction
 * DELETE /api/reactions.php - Remove reaction
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/jwt.php';
require_once __DIR__ . '/helpers/logging.php';

header('Content-Type: application/json; charset=utf-8');
// CORS handled by applyCORS()
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
// CORS handled by applyCORS()

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

try {
    $db = getDB();

    // Check if reactions table exists
    $stmt = $db->query("SHOW TABLES LIKE 'topic_reactions'");
    if ($stmt->rowCount() === 0) {
        echo json_encode(['error' => 'Forum tables not found. Run database/setup.php first.']);
        exit;
    }
    $stmt->closeCursor();

    // Available reactions
    $availableReactions = [
        'like' => '👍',
        'love' => '❤️',
        'laugh' => '😂',
        'wow' => '😮',
        'sad' => '😢',
        'angry' => '😠',
    ];

    // GET - Get reactions for a topic or post
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $topicId = isset($_GET['topic']) ? (int) $_GET['topic'] : null;
        $postId = isset($_GET['post']) ? (int) $_GET['post'] : null;

        if (!$topicId && !$postId) {
            http_response_code(400);
            echo json_encode(['error' => 'topic or post ID required']);
            exit;
        }

        if ($topicId) {
            $stmt = $db->prepare("
                SELECT reaction, COUNT(*) as count
                FROM topic_reactions
                WHERE topic_id = ?
                GROUP BY reaction
            ");
            $stmt->execute([$topicId]);
        } else {
            $stmt = $db->prepare("
                SELECT reaction, COUNT(*) as count
                FROM post_reactions
                WHERE post_id = ?
                GROUP BY reaction
            ");
            $stmt->execute([$postId]);
        }

        $reactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $formatted = [];
        $total = 0;
        foreach ($reactions as $r) {
            if (isset($availableReactions[$r['reaction']])) {
                $formatted[$r['reaction']] = [
                    'icon' => $availableReactions[$r['reaction']],
                    'count' => (int) $r['count'],
                ];
                $total += (int) $r['count'];
            }
        }

        echo json_encode([
            'success' => true,
            'type' => $topicId ? 'topic' : 'post',
            'id' => $topicId ?? $postId,
            'reactions' => $formatted,
            'total' => $total,
            'available' => $availableReactions,
        ]);
        exit;
    }

    // Auth helper
    $authUser = function() use ($db) {
        $token = getJWTFromHeader();
        if (!$token) {
            http_response_code(401);
            echo json_encode(['error' => 'Authentication required']);
            exit;
        }

        $payload = verifyJWT($token);
        if (!$payload) {
            http_response_code(401);
            echo json_encode(['error' => 'Invalid or expired token']);
            exit;
        }

        $user = getUserWithRoles($payload['sub']);
        if (!$user) {
            http_response_code(401);
            echo json_encode(['error' => 'User not found']);
            exit;
        }

        return $user;
    };

    // POST - Add reaction
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }

        $topicId = isset($input['topic_id']) ? (int) $input['topic_id'] : null;
        $postId = isset($input['post_id']) ? (int) $input['post_id'] : null;
        $reaction = sanitize($input['reaction'] ?? 'like');

        if (!$topicId && !$postId) {
            http_response_code(400);
            echo json_encode(['error' => 'topic_id or post_id required']);
            exit;
        }

        if (!isset($availableReactions[$reaction])) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid reaction type', 'available' => array_keys($availableReactions)]);
            exit;
        }

        if ($topicId) {
            // Check topic exists
            $stmt = $db->prepare("SELECT id FROM topics WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$topicId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Topic not found']);
                exit;
            }
            $stmt->closeCursor();

            // Check existing reaction
            $stmt = $db->prepare("SELECT id FROM topic_reactions WHERE topic_id = ? AND user_id = ?");
            $stmt->execute([$topicId, $user['id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($existing) {
                // Update existing reaction
                $stmt = $db->prepare("UPDATE topic_reactions SET reaction = ?, created_at = NOW() WHERE topic_id = ? AND user_id = ?");
                $stmt->execute([$reaction, $topicId, $user['id']]);
            } else {
                // Insert new reaction
                $stmt = $db->prepare("INSERT INTO topic_reactions (topic_id, user_id, reaction) VALUES (?, ?, ?)");
                $stmt->execute([$topicId, $user['id'], $reaction]);

                // Update topic likes count
                $stmt = $db->prepare("UPDATE topics SET likes = likes + 1 WHERE id = ?");
                $stmt->execute([$topicId]);
            }
            $stmt->closeCursor();

            $type = 'topic';
            $id = $topicId;
        } else {
            // Check post exists
            $stmt = $db->prepare("SELECT id FROM posts WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$postId]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['error' => 'Post not found']);
                exit;
            }
            $stmt->closeCursor();

            // Check existing reaction
            $stmt = $db->prepare("SELECT id FROM post_reactions WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $user['id']]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            $stmt->closeCursor();

            if ($existing) {
                // Update existing reaction
                $stmt = $db->prepare("UPDATE post_reactions SET reaction = ?, created_at = NOW() WHERE post_id = ? AND user_id = ?");
                $stmt->execute([$reaction, $postId, $user['id']]);
            } else {
                // Insert new reaction
                $stmt = $db->prepare("INSERT INTO post_reactions (post_id, user_id, reaction) VALUES (?, ?, ?)");
                $stmt->execute([$postId, $user['id'], $reaction]);

                // Update post likes count
                $stmt = $db->prepare("UPDATE posts SET likes = likes + 1 WHERE id = ?");
                $stmt->execute([$postId]);
            }
            $stmt->closeCursor();

            $type = 'post';
            $id = $postId;
        }

        echo json_encode([
            'success' => true,
            'message' => 'Reaction added',
            'type' => $type,
            'id' => $id,
            'reaction' => $reaction,
            'icon' => $availableReactions[$reaction],
        ]);
        exit;
    }

    // DELETE - Remove reaction
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        $user = $authUser();
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data']);
            exit;
        }

        $topicId = isset($input['topic_id']) ? (int) $input['topic_id'] : null;
        $postId = isset($input['post_id']) ? (int) $input['post_id'] : null;

        if (!$topicId && !$postId) {
            http_response_code(400);
            echo json_encode(['error' => 'topic_id or post_id required']);
            exit;
        }

        if ($topicId) {
            $stmt = $db->prepare("DELETE FROM topic_reactions WHERE topic_id = ? AND user_id = ?");
            $stmt->execute([$topicId, $user['id']]);

            // Update topic likes count
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("UPDATE topics SET likes = GREATEST(0, likes - 1) WHERE id = ?");
                $stmt->execute([$topicId]);
            }
            $stmt->closeCursor();
        } else {
            $stmt = $db->prepare("DELETE FROM post_reactions WHERE post_id = ? AND user_id = ?");
            $stmt->execute([$postId, $user['id']]);

            // Update post likes count
            if ($stmt->rowCount() > 0) {
                $stmt = $db->prepare("UPDATE posts SET likes = GREATEST(0, likes - 1) WHERE id = ?");
                $stmt->execute([$postId]);
            }
            $stmt->closeCursor();
        }

        echo json_encode([
            'success' => true,
            'message' => 'Reaction removed',
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => ENVIRONMENT === 'development' ? $e->getMessage() : 'An error occurred',
    ]);
}