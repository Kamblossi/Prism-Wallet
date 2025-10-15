<?php
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/endpoint_helpers.php';

if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        // Only admin can change AI settings now (global)
        if (!current_user_is_admin($pdo)) { json_error(translate('error', $i18n) ?: 'Forbidden', 403, 'forbidden'); }
        require_csrf();
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);

        $aiEnabled = isset($data['ai_enabled']) ? (bool) $data['ai_enabled'] : false;
        $aiType = isset($data['ai_type']) ? trim($data['ai_type']) : '';
        $aiApiKey = isset($data['api_key']) ? trim($data['api_key']) : '';
        $aiOllamaHost = isset($data['ollama_host']) ? trim($data['ollama_host']) : '';
        $aiModel = isset($data['model']) ? trim($data['model']) : '';

        if (empty($aiType) || !in_array($aiType, ['chatgpt', 'gemini', 'ollama'])) {
            $response = [
                "success" => false,
                "message" => translate('error', $i18n)
            ];
            echo json_encode($response);
            exit;
        }

        if (($aiType === 'chatgpt' || $aiType === 'gemini') && empty($aiApiKey)) {
            $response = [
                "success" => false,
                "message" => translate('invalid_api_key', $i18n)
            ];
            echo json_encode($response);
            exit;
        }

        if ($aiType === 'ollama' && empty($aiOllamaHost)) {
            $response = [
                "success" => false,
                "message" => translate('invalid_host', $i18n)
            ];
            echo json_encode($response);
            exit;
        }

        if (empty($aiModel)) {
            $response = [
                "success" => false,
                "message" => translate('fill_mandatory_fields', $i18n)
            ];
            echo json_encode($response);
            exit;
        }

        if ($aiType === 'ollama') {
            $aiApiKey = ''; // Ollama does not require an API key
        } else {
            $aiOllamaHost = ''; // Clear Ollama host if not using Ollama
        }

        // Store globally in admin table
        $stmt = $pdo->prepare('UPDATE admin SET ai_enabled = :en, ai_type = :t, ai_api_key = :k, ai_model = :m, ai_url = :u WHERE id = (SELECT id FROM admin ORDER BY id ASC LIMIT 1)');
        $stmt->execute([':en'=>$aiEnabled?1:0, ':t'=>$aiType, ':k'=>$aiApiKey, ':m'=>$aiModel, ':u'=>$aiOllamaHost]);
        audit_admin_action($pdo, 'ai.save_settings', null, ['type'=>$aiType, 'enabled'=>$aiEnabled?1:0]);

        // PDO conversion - removed result check
            $response = [
                "success" => true,
                "message" => translate('success', $i18n),
                "enabled" => $aiEnabled
            ];
        } else {
            $response = [
                "success" => false,
                "message" => translate('error', $i18n)
            ];
        }
        echo json_encode($response);
    } else {
        $response = [
            "success" => false,
            "message" => translate('invalid_request_method', $i18n)
        ];
        echo json_encode($response);
    }
} else {
    $response = [
        "success" => false,
        "message" => translate('session_expired', $i18n)
    ];
    echo json_encode($response);
}
