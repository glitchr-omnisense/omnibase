<?php

namespace Base\Service\Model\Wysiwyg;

use DOMDocument;

class LinkEnhancer implements LinkEnhancerInterface
{
    /**
     * Rewrites same-site absolute <a href> links back to relative, leaving
     * everything else (external links, already-relative paths, anchors,
     * mailto:/tel:, ...) untouched.
     *
     * An editor authoring/pasting a link always bakes in whatever host they
     * were browsing at the time (EditorJS's built-in inline "link" tool
     * stores the href verbatim, no normalization) - on this app that means
     * an absolute https://m.apfelschorlette.fr/... URL sits in the stored
     * content forever, so viewing that same article on
     * beta.m.apfelschorlette.fr (or any other environment sharing the DB)
     * silently bounces the reader back to production instead of following
     * the link on the current host (reported live).
     *
     * "Same site" is deliberately domain+subdomain, NOT the full host:
     * AdvancedRouter's own getDomain()/getSubdomain()/getMachine() split
     * (see parse_url2()) treats "apfelschorlette.fr" + "m" as the stable
     * site identity and "machine" (beta/empty) as the part that legitimately
     * varies per environment - comparing full hosts would never match beta
     * against prod, which is exactly the case this exists to fix.
     */
    public function enhance(string|array|null $strOrArray, array $attributes = []): string|array|null
    {
        if (!$strOrArray) {
            return $strOrArray;
        }

        $array = $strOrArray;
        if (!is_array($array)) {
            $array = [$array];
        }

        $current = parse_url2(get_url());
        $currentDomain = $current["domain"] ?? null;
        $currentSubdomain = $current["subdomain"] ?? null;

        foreach ($array as &$entry) {

            if (!$entry || !is_string($entry) || !str_contains($entry, "<a")) {
                continue;
            }

            // mb_utf8(), not mb_detect_encoding($entry): unsure, the latter says ASCII for a text
            // that opens on enough of it (an older article's photos kept inline in base64, 2.6MB),
            // and the conversion from ASCII turned every other character into "?" on the page.
            $dom = new DOMDocument('1.0', 'UTF-8');

            // The "<?xml encoding" prefix is load-bearing, NOT decoration: given an
            // HTML fragment with no charset declaration, libxml assumes ISO-8859-1
            // and decodes each UTF-8 byte as its own character, so saveHTML() below
            // re-encodes them and every accent comes back doubled ("é" -> "Ã©").
            // Passing the encoding to the DOMDocument constructor does NOT prevent
            // this - that argument only labels the output document, it has no effect
            // on how loadHTML() decodes its input. Found live: every article whose
            // stored content contains a link (this enhancer's own entry condition)
            // rendered as mojibake on the public site.
            $dom->loadHTML('<?xml encoding="UTF-8" ?>' . mb_utf8($entry), LIBXML_NOERROR);

            $tags = $dom->getElementsByTagName("a");
            if (count($tags) < 1) {
                continue;
            }

            foreach (iterator_to_array($tags) as $tag) {

                $href = $tag->getAttribute("href");

                // Only a fully-qualified absolute URL is a candidate - an
                // already-relative path, an anchor-only "#..." href, or a
                // mailto:/tel: link has nothing to relativize.
                if (!$href || !str_contains($href, "://")) {
                    continue;
                }

                $link = parse_url2($href);
                if (!$link || ($link["domain"] ?? null) === null) {
                    continue;
                }

                if ($link["domain"] !== $currentDomain || ($link["subdomain"] ?? null) !== $currentSubdomain) {
                    continue; // genuinely external - leave absolute
                }

                $relative = $link["path"] ?? "/";
                if (!empty($link["query"])) {
                    $relative .= "?" . $link["query"];
                }
                if (!empty($link["fragment"])) {
                    $relative .= "#" . $link["fragment"];
                }

                $tag->setAttribute("href", $relative);
            }

            $node = $dom->getElementsByTagName('body')->item(0);
            $entry = trim(implode(array_map([$node->ownerDocument, "saveHTML"], iterator_to_array($node->childNodes))));
        }

        return is_array($strOrArray) ? $array : first($array);
    }
}
