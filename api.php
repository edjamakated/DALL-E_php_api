<?PHP
require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class ImageGenerator
{
    private $openai_api_key;
    private $images_folder = 'images';
    private $db;
    private $client;

    public function __construct($openai_api_key)
    {
        $this->openai_api_key = $openai_api_key;
        $this->client = new Client();

        $this->images_folder = __DIR__ . '/' . $this->images_folder;
        if (!file_exists($this->images_folder)) {
            mkdir($this->images_folder, 0755, true);
        }
        $this->db = new PDO('sqlite:' . __DIR__ . '/image_generations.db');
        if ($this->db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='image_generations';")->fetchColumn() === false) {
            $this->db->exec("CREATE TABLE image_generations (id INTEGER PRIMARY KEY AUTOINCREMENT, prompt TEXT, image_url TEXT, image_path TEXT, timestamp TEXT);");
        }
    }

    private function sanitize_input($input)
    {
        return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    }

    private function get_response_format($input_data)
    {
        if (isset($input_data['response_format'])) {
            $response_format = strtolower($this->sanitize_input($input_data['response_format']));
            if ($response_format === 'url' || $response_format === 'b64_json') {
                return $response_format;
            }
        }
        return 'url';
    }

    private function guzzle_request($prompt, $response_format)
    {
        try {
            $response = $this->client->post('https://api.openai.com/v1/images/generations', [
                'json' => [
                    'prompt' => $prompt,
                    'n' => 1,
                    'size' => '1024x1024',
                    'response_format' => $response_format
                ],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $this->openai_api_key
                ],
                'timeout' => 150
            ]);

            return json_decode($response->getBody(), true);
        } catch (RequestException $e) {
            throw new Exception('Guzzle error: ' . $e->getMessage());
        }
    }

    private function save_image($image_url, $prompt)
    {
        $image_data = file_get_contents($image_url);
        $image_name = uniqid() . '.png';
        $image_path = $this->images_folder . '/' . $image_name;
        file_put_contents($image_path, $image_data);

        $timestamp = date("Y-m-d H:i:s");
        $stmt = $this->db->prepare("INSERT INTO image_generations (prompt, image_url, image_path, timestamp) VALUES (:prompt, :image_url, :image_path, :timestamp)");

        $this->db->beginTransaction();
        $stmt->bindParam(':prompt', $prompt);
        $stmt->bindParam(':image_url', $image_url);
        $stmt->bindParam(':image_path', $image_path);
        $stmt->bindParam(':timestamp', $timestamp);
        $stmt->execute();
        $this->db->commit();

        return ['url' => $image_url, 'path' => $image_path];
    }

    public function generate_image($input_data, $request_method)
    {
        $prompt = '';
        if (isset($input_data['prompt'])) {
            $prompt = $this->sanitize_input($input_data['prompt']);
        } else {
            throw new Exception('Missing prompt in request');
        }

        $response_format = $this->get_response_format($input_data);

        $response_data = $this->guzzle_request($prompt, $response_format);

        if ($response_format === 'url' && !isset($response_data['data'][0]['url'])) {
            throw new Exception('Invalid API response url');
        } elseif ($response_format === 'b64_json' && !isset($response_data['data'][0]['image'])) {
            throw new Exception('Invalid API b64 response b64');
        }

        $image_url = '';
        if ($response_format === 'url') {
            $image_url = $response_data['data'][0]['url'];
        } else {
            $image_url = 'data:image/png;base64,' . $response_data['data'][0]['image'];
        }

        $output_data = $this->save_image($image_url, $prompt);

        if ($request_method === 'GET') {
            if ($response_format === 'url') {
                return ['content_type' => 'text/plain', 'data' => $image_url];
            } else {
                return ['content_type' => 'application/json', 'data' => $output_data];
            }
        } else {
            return ['content_type' => 'application/json', 'data' => $output_data];
        }
    }
}
$api_key = 'sk-yourKeyHere'; // enter openai key here
$imageGenerator = new ImageGenerator($api_key);

try {
    $input_data = ($_SERVER['REQUEST_METHOD'] === 'GET') ? $_GET : json_decode(file_get_contents('php://input'), true);
    $response = $imageGenerator->generate_image($input_data, $_SERVER['REQUEST_METHOD']);
    header('Content-Type: ' . $response['content_type']);

    if ($response['content_type'] === 'application/json') {
        echo json_encode($response['data']);
    } else {
        echo $response['data'];
    }
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
}
