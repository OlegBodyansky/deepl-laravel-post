<?php

namespace OlegBodyansky\DeepL\Laravel\Wrappers;

use OlegBodyansky\DeepL\Api\DeepLApiClient;
use Illuminate\Contracts\Config\Repository;
use OlegBodyansky\DeepL\Api\Exceptions\ApiException;
use OlegBodyansky\DeepL\Api\Cons\Translate as TranslateType;

/**
 * Class DeepLApiWrapper.
 */
class DeepLApiWrapper
{
    /**
     * @var Repository
     */
    protected $config;

    /**
     * @var DeepLApiClient
     */
    protected $client;

    /**
     * MollieApiWrapper constructor.
     *
     * @param Repository     $config
     * @param DeepLApiClient $client
     *
     * @throws \OlegBodyansky\DeepL\Api\Exceptions\ApiExcesption
     * @return void
     */
    public function __construct(Repository $config, DeepLApiClient $client)
    {
        $this->config = $config;
        $this->client = $client;

        $this->setApiKey($this->config->get('deepl.key'));
    }

    /**
     * @param string $url
     */
    public function setApiEndpoint($url)
    {
        $this->client->setApiEndpoint($url);
    }

    /**
     * @return string
     */
    public function getApiEndpoint()
    {
        return $this->client->getApiEndpoint();
    }

    /**
     * @param string $api_key The DeepL API key.
     *
     * @throws ApiException
     */
    public function setApiKey($api_key)
    {
        $this->client->setApiKey($api_key);
    }

    /**
     * @return \OlegBodyansky\DeepL\Api\Endpoints\UsageEndpoint
     */
    public function usage()
    {
        return $this->client->usage;
    }

    /**
     * @return \OlegBodyansky\DeepL\Api\Endpoints\TranslateEndpoint
     */
    public function translations()
    {
        return $this->client->translations;
    }

    /**
     * Translate a collection of translations with \OlegBodyansky\DeepL\Api\Resources\Translate items from DeepL.
     *
     * @param string $text
     * @param string $to
     * @param string $from
     * @param array  $options
     *
     * @return \OlegBodyansky\DeepL\Api\Resources\BaseResource|\OlegBodyansky\DeepL\Api\Resources\Translate
     * @throws \OlegBodyansky\DeepL\Api\Exceptions\ApiException
     */
    public function translate(
        $text,
        $to = TranslateType::LANG_EN,
        $from = TranslateType::LANG_AUTO,
        $options = [],
        $filters = []
    ) {
        $regexTemp = [];

        // Prevent translating the :keys (and make it cheaper)
        if (!array_get($options, 'translate_keys', false) && preg_match_all('~(:\w+)~', $text, $matches, PREG_PATTERN_ORDER)) {
            foreach ($matches[1] as $key => $word) {
                $regexTemp["_{$key}"] = $word;
            }

            $text = str_replace(array_values($regexTemp), array_keys($regexTemp), $text);
        }

        $response = $this->client->translations->translate($text, $to, $from, $options = [], $filters = []);

        // Trim the text
        foreach ($response->translations as $key => $translation) {
            $response->translations[$key]->text = $this->trimText($translation->text);
        }

        if (!empty($regexTemp)) {
            foreach ($response->translations as $key => $translation) {
                $response->translations[$key]->text = str_replace(array_keys($regexTemp), array_values($regexTemp), $translation->text);
            }
        }

        return $response;
    }

    /**
     * Translate a text with DeepL.
     *
     * @param string $text
     * @param string $to
     * @param string $from
     * @param array  $options
     *
     * @return string
     * @throws \OlegBodyansky\DeepL\Api\Exceptions\ApiException
     */
    public function translateText(
        $text,
        $to = TranslateType::LANG_EN,
        $from = TranslateType::LANG_AUTO,
        $options = []
    ) {
        return $this->trimText($this->translate($text, $to, $from, $options)->translations[0]->text);
    }

    /**
     * Trim the text.
     *
     * @param $text
     *
     * @return string
     */
    public function trimText($text): string
    {
        $to   = [];
        $from = [];

        if (!empty($trims = array_get($this->config->get('deepl'), 'trim.space_before_char', []))) {
            $from[] = '/\s([' . implode('|', $trims) . '])\s/';
            $to[]   = '${1} ';
            $from[] = '/\s([' . implode('|', $trims) . '])$/';
            $to[]   = '${1}';
        }

        if (!empty($trims = array_get($this->config->get('deepl'), 'trim.spaces_between_char', []))) {
            foreach ($trims as $trim) {
                $to[]   = $trim . '${1}' . $trim;
                $from[] = "/{$trim}\s(.*?)\s{$trim}/";
            }
        }

        if (!empty($from) && !empty($to) && count($from) === count($to)) {
            $text = preg_replace($from, $to, $text);
        }

        return $text;
    }
}
