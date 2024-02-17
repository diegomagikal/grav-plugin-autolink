<?php
namespace Grav\Plugin;
use Grav\Common\Plugin;
use RocketTheme\Toolbox\Event\Event;

class AutoLinkPlugin extends Plugin
{

    private $cacheKeys = [];

    public static function getSubscribedEvents()
    {
        return [
            'onPageContentRaw' => ['onPageContentRaw', 0],
            'onCacheFlushed' => ['onCacheFlushed', 0]
        ];
    }

    public function onCacheFlushed()
{
    $cache = $this->grav['cache'];
        foreach ($this->cacheKeys as $cacheKey) {
            $cache->delete($cacheKey);
        }

        $this->cacheKeys = [];
}

    public function onPageContentRaw(Event $event)
    {
        $page = $event['page'];
        $cache = $this->grav['cache'];
        $cacheKey = $page->filePathClean() . '-autolink';
        $this->cacheKeys[] = $cacheKey;

        if ($cachedContent = $cache->fetch($cacheKey)) {
            $page->setRawContent($cachedContent);
            return;
        }

        $content = $page->getRawContent();
        $home_url = $this->grav['uri']->rootUrl(true);
        $currentPageUrl = $this->grav['uri']->url(true);
        $languageConfig = $this->grav['config']->get('system.languages');
        $includeLangCode = $languageConfig['include_default_lang'] ?? false;
        $currentLang = $this->grav['language']->getLanguage();


        $links = (array)$this->config->get('plugins.autolink.' . $currentLang) + $this->getTaxonomyLinks();
        $substituteAll = $this->config->get('plugins.autolink.substitute_all', false);

        foreach ($links as $word => $link) {
            $pattern = '/\b' . preg_quote($word, '/') . '\b/i';
            $langPrefix = $includeLangCode ? '/' . $currentLang : '';
            $fullLink = $this->isAbsoluteUrl($link) ? $link : $home_url . $langPrefix . '/' . ltrim($link, '/');

            if ($currentPageUrl === $fullLink || $this->isLinkOverlapped($content, $word)) {
                continue;
            }

            $replacement = "<a href='$fullLink'>$0</a>";
            $content = $substituteAll ? preg_replace($pattern, $replacement, $content) : preg_replace($pattern, $replacement, $content, 1);
        }

        $cache->save($cacheKey, $content);
        $page->setRawContent($content);
    }

    private function getTaxonomyLinks()
    {
        $links = [];
        if ($this->config->get('plugins.autolink.enable_categories', false)) {
            $categoryFormat = $this->config->get('plugins.autolink.category_link_format', '/categoria?name={category}');
            foreach ((new Taxonomylist)->get()['category'] ?? [] as $category => $items) {
                $links[$category] = str_replace('{category}', urlencode($category), $categoryFormat);
            }
        }

        if ($this->config->get('plugins.autolink.enable_tags', false)) {
            $tagFormat = $this->config->get('plugins.autolink.tag_link_format', '/tag?name={tag}');
            foreach ((new Taxonomylist)->get()['tag'] ?? [] as $tag => $items) {
                $links[$tag] = str_replace('{tag}', urlencode($tag), $tagFormat);
            }
        }

        return $links;
    }

    private function isLinkOverlapped($content, $word)
    {
        $pattern = '/<a [^>]*?href=[^>]*?\b' . preg_quote($word, '/') . '\b[^>]*?>.*?<\/a>/is';

        return preg_match($pattern, $content);
    }


    private function isAbsoluteUrl($url)
    {
        return preg_match('/^(http|https):\/\/[^ "]+$/', $url);
    }

}
