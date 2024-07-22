<?php

namespace Leantime\Plugins\ProjectOverview\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;
use Leantime\Core\Environment;
use Leantime\Core\IncomingRequest;
use Symfony\Component\HttpFoundation\Response;
use Leantime\Core\Language;

// https://github.com/Leantime/plugin-template/blob/main/Middleware/GetLanguageAssets.php
class GetLanguageAssets
{
    public function __construct(
        private Language $language,
        private Environment $config,
    ) {}

    /**
     * @param \Closure(IncomingRequest): Response $next
     * @throws BindingResolutionException
     **/
    public function handle(IncomingRequest $request, Closure $next): Response
    {
        $languageArray = Cache::get('plugintemplate.languageArray', []);

        if (! empty($languageArray)) {
            $this->language->ini_array = array_merge($this->language->ini_array, $languageArray);
            return $next($request);
        }

        if (! Cache::store('installation')->has('plugintemplate.language.en-US')) {
            $languageArray += parse_ini_file(__DIR__ . '/../Language/en-US.ini', true);
        }

        if (($language = $_SESSION["usersettings.language"] ?? $this->config->language) !== 'en-US') {
            if (! Cache::store('installation')->has('plugintemplate.language.' . $language)) {
                Cache::store('installation')->put(
                    'plugintemplate.language.' . $language,
                    parse_ini_file(__DIR__ . '/../Language/' . $language . '.ini', true)
                );
            }

            $languageArray = array_merge($languageArray, Cache::store('installation')->get('plugintemplate.language.' . $language));
        }

        Cache::put('plugintemplate.languageArray', $languageArray);

        $this->language->ini_array = array_merge($this->language->ini_array, $languageArray);
        return $next($request);
    }
}

