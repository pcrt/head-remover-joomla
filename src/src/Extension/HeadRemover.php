<?php
/**
 * @package     HeadRemover
 * @copyright   (C) 2026 Protocolli Creativi
 * @license     GPL-2.0-or-later
 */

namespace Pcrt\Plugin\System\HeadRemover\Extension;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\WebAsset\WebAssetManager;
use Joomla\Event\EventInterface;
use Joomla\Event\SubscriberInterface;

\defined('_JEXEC') or die;

/** Controls registered HTML head assets without changing Joomla's registry. */
final class HeadRemover extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    private bool $processed = false;

    private string $motd = '';

    public static function getSubscribedEvents(): array
    {
        return [
            'onBeforeCompileHead' => ['onBeforeCompileHead', -1000],
            'onAfterRender' => ['onAfterRender', -1000],
        ];
    }

    private function applies(): bool
    {
        $app = $this->getApplication();

        if ($app->isClient('administrator') && (bool) $this->params->get('exclude_backend', 1)) {
            return false;
        }

        return $app->isClient('site')
            || (!(bool) $this->params->get('frontend_only', 1) && $app->isClient('administrator'));
    }

    public function onBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $doc = $event->getDocument();

        if ($this->processed || !$this->applies() || !$doc instanceof HtmlDocument) {
            return;
        }

        $this->processed = true;
        $wa = $doc->getWebAssetManager();
        $rules = $this->rows('remove_urls');
        $this->removeAssets($wa, $rules);

        // Only write the changed Document keys: restoring assetManager would re-enable assets.
        $head = $doc->getHeadData();
        $changes = [];

        foreach (['scripts' => 'script', 'styleSheets' => 'style'] as $key => $type) {
            $changes[$key] = $head[$key] ?? [];

            foreach (array_keys($changes[$key]) as $url) {
                if ($this->matchesUrl($url, $type, $rules)) {
                    unset($changes[$key][$url]);
                }
            }
        }

        $changes['metaTags'] = $head['metaTags'] ?? [];

        foreach (['generator', 'author'] as $name) {
            if (!(bool) $this->params->get('remove_' . $name, 0)) {
                continue;
            }

            foreach ($changes['metaTags'] as &$tags) {
                foreach (array_keys($tags) as $key) {
                    if (strtolower($key) === $name) {
                        unset($tags[$key]);
                    }
                }
            }
            unset($tags);

            if ($name === 'generator') {
                $doc->setGenerator('');
            }
        }

        $doc->setHeadData($changes);
        $this->addAssets($wa, 'script', 'scripts');
        $this->addAssets($wa, 'style', 'styles');

        // Custom tags preserve interleaved name/property/http-equiv rows and duplicates.
        foreach ($this->rows('metas') as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            $type = (string) ($row['kind'] ?? 'name');

            if (!in_array($type, ['name', 'property', 'http-equiv'], true) || $name === '') {
                continue;
            }

            $doc->addCustomTag('<meta ' . $type . '="' . $this->escape($name)
                . '" content="' . $this->escape((string) ($row['content'] ?? '')) . '">');
        }

        // A comment added with addCustomTag would appear at the end of the head.
        // Prepare it here; place it immediately after <head> once Joomla has rendered.
        $message = trim((string) $this->params->get('motd', ''));
        $message = str_replace(['--', '<', '>'], ['—', '‹', '›'], $message);
        $this->motd = $message === '' ? '' : "\n<!-- " . $message . " -->\n";
    }

    /** The final HTML is used only to place the prepared MOTD, never to rewrite assets. */
    public function onAfterRender(EventInterface $event): void
    {
        if ($this->motd === '' || !$this->applies()) {
            return;
        }

        $app = $this->getApplication();
        $comment = $this->motd;
        $body = preg_replace_callback('/<head\b[^>]*>/i', static fn ($match) => $match[0] . $comment, $app->getBody(), 1);

        if ($body !== null) {
            $app->setBody($body);
        }

        $this->motd = '';
    }

    private function removeAssets(WebAssetManager $wa, array $rules): void
    {
        $patterns = array_filter(array_map('trim', explode("\n", str_replace("\r", '', (string) $this->params->get('core_assets', '')))));
        $active = [];
        $remove = [];

        foreach (['script', 'style'] as $type) {
            foreach ($wa->getAssets($type, true) as $name => $asset) {
                $id = $type . ':' . $name;
                $active[$id] = [$type, $name, $asset];

                foreach ($patterns as $pattern) {
                    // Only a trailing .* wildcard is supported, e.g. bootstrap.*.
                    $match = str_ends_with($pattern, '.*')
                        ? str_starts_with($name, substr($pattern, 0, -1))
                        : $name === $pattern;

                    if ($match) {
                        $remove[$id] = true;
                    }
                }

                if ($this->matchesUrl($asset->getUri(false), $type, $rules)
                    || $this->matchesUrl($asset->getUri(), $type, $rules)) {
                    $remove[$id] = true;
                }
            }
        }

        // WAM automatically re-enables dependencies. Disable their active dependants too,
        // otherwise removing jquery/core would silently have no effect.
        do {
            $changed = false;

            foreach ($active as $id => [$type, $name, $asset]) {
                if (isset($remove[$id])) {
                    continue;
                }

                foreach ($asset->getDependencies() as $dependency) {
                    $parts = explode('#', $dependency, 2);
                    $dependencyId = ($parts[1] ?? $type) . ':' . $parts[0];

                    if (isset($remove[$dependencyId])) {
                        $remove[$id] = true;
                        $changed = true;
                        break;
                    }
                }
            }
        } while ($changed);

        foreach (array_keys($remove) as $id) {
            [$type, $name] = $active[$id];
            $wa->disableAsset($type, $name);
        }
    }

    private function matchesUrl(string $url, string $type, array $rules): bool
    {
        if ($url === '') {
            return false;
        }

        foreach ($rules as $rule) {
            $fragment = trim((string) ($rule['url'] ?? ''));
            $ruleType = (string) ($rule['type'] ?? 'both');

            if ($fragment !== '' && in_array($ruleType, ['both', $type], true) && str_contains($url, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function addAssets(WebAssetManager $wa, string $type, string $parameter): void
    {
        $previous = [];

        foreach ($this->rows($parameter) as $index => $row) {
            $url = $this->assetUrl((string) ($row['url'] ?? ''));

            if ($url === '') {
                continue;
            }

            $attributes = [];

            if ($type === 'script') {
                $mode = (string) ($row['mode'] ?? '');

                if ($mode === 'module') {
                    $attributes['type'] = 'module';
                } elseif (in_array($mode, ['defer', 'async'], true)) {
                    $attributes[$mode] = true;
                }
            } else {
                $attributes['media'] = trim((string) ($row['media'] ?? 'all')) ?: 'all';
            }

            $name = 'plg.system.headremover.' . $type . '.' . $index;
            $wa->registerAsset($type, $name, $url, ['version' => false], $attributes, $previous);
            $wa->useAsset($type, $name);
            $previous = [$name];
        }
    }

    /** Restrict to HTTP(S) or local URLs; local paths start at the Joomla root. */
    private function assetUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '' || preg_match('/[\x00-\x20<>"\x27\\\\]/', $url)) {
            return '';
        }

        if (str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme !== null && $scheme !== false) {
            return in_array(strtolower($scheme), ['http', 'https'], true) ? $url : '';
        }

        // Absolute HTTP URLs also avoid WAM treating a query string or a Joomla
        // subdirectory prefix as a filesystem path during local asset resolution.
        if (str_starts_with($url, '/')) {
            $base = new Uri(Uri::root());

            return $base->toString(['scheme', 'host', 'port']) . $url;
        }

        return rtrim(Uri::root(), '/') . '/' . $url;
    }

    private function rows(string $name): array
    {
        $value = $this->params->get($name, []);

        if (is_string($value)) {
            $value = json_decode($value, true) ?? [];
        }

        $result = [];

        foreach ((array) $value as $row) {
            if (is_array($row) || is_object($row)) {
                $result[] = (array) $row;
            }
        }

        return $result;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
