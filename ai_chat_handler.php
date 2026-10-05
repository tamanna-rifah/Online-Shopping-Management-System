<?php
session_start();
require_once 'db.php';
require_once 'ai_config.php';

header('Content-Type: application/json');

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method.']);
    exit;
}

// Get the POST data
$input = json_decode(file_get_contents('php://input'), true);
$userMessage = $input['message'] ?? '';
$selectedModel = $input['model'] ?? 'gemini';

if (empty(trim($userMessage))) {
    echo json_encode(['error' => 'Message cannot be empty.']);
    exit;
}

if (GEMINI_API_KEY === 'YOUR_GEMINI_API_KEY_HERE' || empty(GEMINI_API_KEY)) {
    echo json_encode(['reply' => 'Hi! The AI shopping assistant is currently unconfigured. Please ask the site administrator to add their Gemini API Key in the ai_config.php file.']);
    exit;
}

// 1. Dynamic Database Search based on Keywords
$context = "Relevant Products in our store based on user's query:\n";

// Simple keyword extraction (remove common stop words and punctuation)
$cleanMessage = preg_replace('/[^a-zA-Z0-9\s]/', '', strtolower($userMessage));
$stopWords = ['is', 'are', 'do', 'does', 'have', 'the', 'a', 'an', 'what', 'price', 'cost', 'how', 'much', 'any', 'some', 'please', 'can', 'you', 'show', 'me', 'tell', 'about', 'want', 'buy', 'looking', 'for', 'product'];
$words = array_diff(explode(' ', $cleanMessage), $stopWords);
$keywords = array_filter($words, function($w) { return strlen($w) > 2; });

try {
    if (!empty($keywords)) {
        // Build dynamic LIKE query
        $likeConditions = [];
        $params = [];
        $types = "";
        foreach ($keywords as $word) {
            $likeConditions[] = "(name LIKE ? OR category LIKE ?)";
            $params[] = "%$word%";
            $params[] = "%$word%";
            $types .= "ss";
        }
        
        $sql = "SELECT id, name, category, price FROM products WHERE " . implode(' OR ', $likeConditions) . " LIMIT 10";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            $result = false;
        }
    } else {
        // Fallback to latest 10 products if no keywords found
        $result = $conn->query("SELECT id, name, category, price FROM products ORDER BY id DESC LIMIT 10");
    }

    if (isset($result) && $result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $productLink = "guest_p_details.php?id=" . $row['id'];
            $context .= "- " . $row['name'] . " (Category: " . $row['category'] . ", Price: Tk " . $row['price'] . ") - Link: " . $productLink . "\n";
        }
    } else {
        $context .= "No specific products found matching the query. We might be out of stock.\n";
    }
} catch (Exception $e) {
    $context .= "Failed to load product catalog dynamically.\n";
}

// 2. Build the System Prompt
$systemInstruction = "You are a helpful, friendly, and knowledgeable AI shopping assistant for 'Dress at Your Door'. " .
                     "Your job is to help customers find products, answer questions about sizing, pricing, and general policies. " .
                     "Use the following product list as your source of truth:\n\n" .
                     $context . "\n\n" .
                     "Rules:\n" .
                     "1. Only recommend products from the list above.\n" .
                     "2. Keep your answers concise and friendly.\n" .
                     "3. If a user asks for something not in the list, politely inform them that we don't carry that item right now.\n" .
                     "4. Prices are in Tk (Taka).\n" .
                     "5. When suggesting a product, always provide its link using Markdown format, e.g., [Product Name](link).";

// 3. Call the API based on selected model
if ($selectedModel === 'openai' || $selectedModel === 'gpt-oss-20b') {
    $url = 'https://openrouter.ai/api/v1/chat/completions';
    
    $data = [
        'model' => OPENAI_MODEL,
        'messages' => [
            ['role' => 'system', 'content' => $systemInstruction],
            ['role' => 'user', 'content' => $userMessage]
        ],
        'temperature' => 0.7,
        'max_tokens' => 500
    ];
    
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . OPENAI_API_KEY,
        'HTTP-Referer: http://localhost', // Required by OpenRouter
        'X-Title: Dress at Your Door' // Required by OpenRouter
    ];
} else {
    // Default to Gemini
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . GEMINI_MODEL . ':generateContent';
    
    $data = [
        'system_instruction' => [
            'parts' => [
                ['text' => $systemInstruction]
            ]
        ],
        'contents' => [
            [
                'parts' => [
                    ['text' => $userMessage]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 500,
        ]
    ];
    
    $headers = [
        'Content-Type: application/json',
        'X-goog-api-key: ' . GEMINI_API_KEY
    ];
}

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Bypass local SSL issues
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    echo json_encode(['reply' => 'Sorry, I am having trouble connecting to my brain right now. Please try again later!']);
    exit;
}

if ($httpCode !== 200) {
    // Return the response directly for debugging
    echo json_encode(['reply' => "I encountered a slight issue while thinking (HTTP $httpCode). Debug info: " . strip_tags($response)]);
    exit;
}

$responseData = json_decode($response, true);
$replyText = 'Sorry, I could not generate a response.';

if ($selectedModel === 'openai' || $selectedModel === 'gpt-oss-20b') {
    $replyText = $responseData['choices'][0]['message']['content'] ?? $replyText;
} else {
    $replyText = $responseData['candidates'][0]['content']['parts'][0]['text'] ?? $replyText;
}

echo json_encode(['reply' => $replyText]);
