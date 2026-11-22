<?php

namespace App\Api;

use Fusion\Core\Request;

final class ApiModel
{
    public function __construct(private Request $request)
    {
    }

    public function endpoints(): array
    {
        return [
            ['method' => 'GET', 'path' => $this->request->apiUrl(), 'description' => 'Application metadata and endpoints'],
            ['method' => 'GET', 'path' => $this->request->apiUrl('/framework'), 'description' => 'Getting-started guide data'],
            ['method' => 'GET', 'path' => $this->request->apiUrl('/templates'), 'description' => 'Template examples'],
            ['method' => 'GET', 'path' => $this->request->apiUrl('/services'), 'description' => 'Core service examples'],
            ['method' => 'GET', 'path' => $this->request->apiUrl('/classes'), 'description' => 'Full class reference'],
            ['method' => 'POST', 'path' => $this->request->apiUrl('/example'), 'description' => 'Validate and echo a name'],
        ];
    }

    public function page(): array
    {
        $example = rtrim(config('app.url'), '/') . $this->request->apiUrl('/example');
        return [
            'title' => 'API guide',
            'endpoints' => $this->endpoints(),
            'command' => 'curl -i -H "Content-Type: application/json" -d \'{"name":"Ada"}\' ' . escapeshellarg($example),
            'response' => '{"data":{"name":"Ada"}}',
            'validationError' => '{"error":"Validation failed","status":422,"errors":{"name":["Name must be at least 2 characters."]}}',
            'greetingCommand' => 'curl -i -H "Content-Type: application/json" -d \'{"name":"Ada"}\' '
                . escapeshellarg(rtrim(config('app.url'), '/') . $this->request->apiUrl('/greeting')),
            'greetingController' => <<<'EXAMPLE'
<?php

namespace App\Greeting;

use Fusion\Core\Controller;

final class GreetingController extends Controller
{
    protected function api(): array
    {
        return ['greeting' => ['POST' => 'create']];
    }

    public function create(): void
    {
        if (!$this->request->isJson()) {
            $this->abort(415, 'Use Content-Type: application/json');
        }

        $data = $this->validate(['name' => 'required|string|min:2|max:80']);
        $this->json(['data' => ['name' => $data['name']]]);
    }
}
EXAMPLE,
            'controller' => <<<'EXAMPLE'
protected function api(): array
{
    return [
        'example' => ['POST' => 'example'],
    ];
}

public function example(): void
{
    if (!$this->request->isJson()) {
        $this->abort(415, 'Use Content-Type: application/json');
    }
    $data = $this->validate(['name' => 'required|string|min:2|max:80']);
    $this->json(['data' => ['name' => $data['name']]]);
}
EXAMPLE,
        ];
    }
}
