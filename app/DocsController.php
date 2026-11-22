<?php

namespace App;

use Fusion\Core\Controller;
use Fusion\Core\Template;
use Fusion\Core\View;

abstract class DocsController extends Controller
{
    public function view(string $template, array $data = []): void
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $template)) {
            throw new \InvalidArgumentException('Invalid page template name');
        }

        $module = strtolower(explode('\\', static::class)[1]);
        $data = array_merge([
            'docsPage' => $module,
            'siteName' => config('app.name'),
            'homeUrl' => $this->request->url(),
            'frameworkUrl' => $this->request->url('/framework'),
            'templatesUrl' => $this->request->url('/templates'),
            'apiGuideUrl' => $this->request->url('/api-guide'),
            'servicesUrl' => $this->request->url('/services'),
            'pluginsUrl' => $this->request->url('/plugins'),
            'stylesUrl' => $this->request->url('/fusionphpstyle'),
            'appearanceUrl' => $this->request->url('/appearance'),
            'appearanceScriptUrl' => $this->request->url('/assets/js/appearance.js', true),
            'classesUrl' => $this->request->url('/classes'),
            'docsCssUrl' => $this->request->url('/assets/css/docs.css', true),
            'fusionScriptUrl' => $this->request->url('/assets/js/fusionphp.js', true),
            'apiExampleUrl' => $this->request->apiUrl('/example'),
            'fusionJsGuideUrl' => $this->request->url('/fusionphpstyle') . '#javascript',
            'styleCssUrl' => $this->request->url('/assets/css/fusionphpstyle.css', true),
            'apiEnabled' => config('app.mode') !== 'web',
            'apiRootUrl' => $this->request->apiUrl(),
        ], $data);
        $page = Template::render(BASE_PATH . "/app/{$module}/{$template}.fpf", $data);
        (new View(BASE_PATH . '/app/layouts/docs-content.php', $data + [
            'pageContent' => CodeExamples::highlight($page),
        ]))->render();
    }
}
