<?php
class Translator extends TranslatorBase {
    /* @var ProjectConfig $projectConfig */
    private $projectConfig;
    /* @var Logger $logger */
    public static $logger;
    /* @var PathBuilder $pathBuilder */
    private $pathBuilder;
    /* @var Map|JList[]|TranslationEntry[][] $normalizedLookup */
    private $normalizedLookup;
    /* @var JList|TranslationEntry[] $manualEntries */
    private $manualEntries;
    /* @var Map|TranslationEntry[] $swapEntries */
    private $swapEntries;
    /* @var NodeSet $missingNodes */
    private $missingNodes;
    /* @var NodeSet $handledMissingNodes */
    private $handledMissingNodes;
    /* @var Project $p */
    private $p;
    /* @var int $entriesOnPage */
    private $entriesOnPage, $entriesWithTranslation;
    /* @var BlockXMLBuilder $blockXMLBuilder */
    private $blockXMLBuilder;
    /* @var Pattern $ignore */
    private $ignore;
    /* @var Pattern $noTranslateClassMatcher */
    private $noTranslateClassMatcher;
    /* @var IntToNodeMap $idToHTMLNode */
    private $idToHTMLNode;
    /* @var NodeMap $blockToBlockXML */
    private $blockToBlockXML;
    /* @var CompatibleXMLSerializer $serializer */
    private $serializer;
    /* @var boolean $justScan */
    private $justScan;
    /* @var boolean $annotationOn */
    private $annotationOn;
    /* @var DOMDocument $xml */
    private $xml;
    /* @var JList|String[] $noTranslateClasses */
    public static $noTranslateClasses;
    /* @var Pattern $CSS_WHITE_SPACE_REPLACE */
    public static $CSS_WHITE_SPACE_REPLACE;
    /* @var Pattern $LAST_ELEMENT_NAME */
    public static $LAST_ELEMENT_NAME;
    /* @var Pattern $MANUAL_ENTRY_PATH */
    public static $MANUAL_ENTRY_PATH;
    /* @var Pattern $SWAP_ELEMENT_ENTRY_PATH */
    public static $SWAP_ELEMENT_ENTRY_PATH;

    protected function __construct() {
        $this->missingNodes = new NodeSet();
        $this->handledMissingNodes = new NodeSet();
        $this->idToHTMLNode = null;
        $this->blockToBlockXML = new NodeMap();
        $this->serializer = new CompatibleXMLSerializer();
        $this->justScan = false;
        $this->annotationOn = false;
    }


    /**
     * @param String $projectCode
     * @param Project $project
     * @return Translator
     */
    public static function create($projectCode, $project) {
        $instance = new Translator();
        $instance->initVariables($projectCode, $project);
        return $instance;
    }

    /**
     * @param String $projectCode
     * @param Project $project
     * @return Translator
     */
    protected function initVariables($projectCode, $project) {
        if ($project != null) $this->p = $project; else $this->p = Project::getProject(Project::keyForCode($projectCode));
        $this->projectConfig = $this->p->getProjectConfig();
        $this->noTranslateClassMatcher = EncodeUtil::matcherForClassNames($this->getIgnoreClasses());
    }

    /**
     * @return JList|String[]
     */
    private function getIgnoreClasses()
    {
        /* @var JList|String[] $ignoreClasses */ $ignoreClasses = Lists::newArrayList();
        $ignoreClasses->addAll($this->p->getIgnoreClasses());
        $ignoreClasses->addAll(Translator::$noTranslateClasses);
        return $ignoreClasses;
    }

    /**
     * @param String $ignoreRegexp
     * @return void
     */
    public function setIgnoreRegexp($ignoreRegexp)
    {
        try {
            if ($ignoreRegexp != null) {
                $this->ignore = Pattern::compile($ignoreRegexp);
            }
        } catch (PatternSyntaxException $ex) {
            Translator::$logger->warn("Invalid regexp set for project", $ex);
        }
    }

    /**
     * @param Map|JList[]|TranslationEntry[][] $nl
     * @return void
     */
    public function setNormalizedLookup($nl)
    {
        $this->normalizedLookup = $nl;
    }

    /**
     * @param JList|TranslationEntry[] $entries
     * @return void
     */
    public function setManualEntries($entries)
    {
        $this->manualEntries = $entries;
    }

    /**
     * @param IntToNodeMap $mapping
     * @return void
     */
    public function setIdToHTMLNode($mapping)
    {
        $this->idToHTMLNode = $mapping;
    }

    /**
     * @param DOMDocument $doc
     * @return void
     */
    public function translateDocument($doc)
    {
        $this->pathBuilder = new PathBuilder($doc);
        $this->swapLinks($doc);
        $this->startDeferredUpdates();
        $this->xml = XMLUtil::createDocument();
        $this->blockXMLBuilder = new BlockXMLBuilder($this->xml, $this->projectConfig->getDontTranslatePattern(), $this->noTranslateClassMatcher, $this->ignore, $this->projectConfig->getBlockElements());
        $this->resetXML();
        $this->translate($doc->documentElement, true);
        $this->injectManuals($doc);
        $this->finishDeferredUpdates();
        $this->handleMissing();
    }

    /**
     * @return void
     */
    private function resetXML()
    {
        $this->resetXMLWithRoot($this->xml->createElement("block"));
    }

    /**
     * @param DOMElement $newRoot
     * @return void
     */
    private function resetXMLWithRoot($newRoot)
    {
        if ($this->xml->firstChild != null) $this->xml->removeChild($this->xml->firstChild);
        $this->xml->appendChild($newRoot);
    }

    /**
     * @param DOMDocument $doc
     * @return void
     */
    private function injectManuals($doc)
    {
        if ($this->justScan) return;
        /* @var StringSet $translated */ $translated = new StringSet();
        foreach($this->manualEntries as $te) {
            /* @var String $normalSource */ $normalSource = $te->getNormalizedSource()->toString();
            /* @var Matcher $m */ $m = Translator::$LAST_ELEMENT_NAME->matcher($te->getPath());
            if ($m->find()) {
                /* @var DOMNode $match */ $match = null;
                /* @var int $matchLen */ $matchLen = -1;
                /* @var String $matchPath */ $matchPath = null;
                /* @var String $nodeName */ $nodeName = $m->group(1);
                /* @var String $attributeName */ $attributeName = $m->group(2);
                /* @var boolean $textNode */ $textNode = false;
                if (CompatibleString::getStr($attributeName)->charAt(0) == '%') {
                    $attributeName = CompatibleString::getStr($attributeName)->substring(1);
                } else {
                    $textNode = true;
                }
                /* @var DOMNodeList $elements */ $elements = $doc->getElementsByTagName($nodeName);
                /* @var int $l */ $l = $elements->length;
                for (/* @var int $i */ $i = 0; $i < $l; ++$i) {
                    /* @var DOMElement $e */ $e = /*DOMElement*/ $elements->item($i);
                    /* @var DOMNode $attr */ $attr = null;
                    if ($textNode) {
                        $attr = $e->firstChild;
                    } else {
                        $attr = $e->getAttributeNode($attributeName);
                    }
                    if ($attr == null) continue;
                    /* @var String $path */ $path = $this->pathBuilder->nodePath($attr);
                    if ($translated->contains($path)) continue;
                    /* @var int $thisMatchLen */ $thisMatchLen = $this->prefixMatchLength(CompatibleString::getStr("manual:-1/")->concat($path), $te->getPath());
                    if ((strcmp($normalSource, TranslateUtil::normalizePlainText($attr->nodeValue)->toString()) == 0) && $matchLen < $thisMatchLen) {
                        $match = $attr;
                        $matchLen = $thisMatchLen;
                        $matchPath = $path;
                    }
                }
                if ($match != null) {
                    $this->assignRecordToNode($match, $te, null);
                    ++$this->entriesOnPage;
                    if (!$te->isTargetEmpty()) {
                        $match->nodeValue = ($te->getTarget());
                        ++$this->entriesWithTranslation;
                    }
                    $translated->add($matchPath);
                }
            }
        }
    }

    /**
     * @param DOMDocument $doc
     * @return void
     */
    private function swapLinks($doc)
    {
        if ($this->justScan) return;
        $this->swapResources($doc, "img", "src");
        $this->swapResources($doc, "input", "src");
        $this->swapResources($doc, "a", "href");
        $this->swapResources($doc, "param", "value");
        $this->swapResources($doc, "embed", "src");
        $this->swapResources($doc, "iframe", "src");
        $this->swapResources($doc, "script", "src");
        $this->swapStyles($doc->documentElement);
    }

    /**
     * @param DOMDocument $doc
     * @param String $tagName
     * @param String $attributeName
     * @return void
     */
    private function swapResources($doc, $tagName, $attributeName)
    {
        /* @var DOMNodeList $tags */ $tags = $doc->getElementsByTagName($tagName);
        for (/* @var int $i */ $i = 0, $l = $tags->length; $i < $l; ++$i) {
            /* @var DOMElement $tag */ $tag = /*DOMElement*/ $tags->item($i);
            /* @var String $src */ $src = $this->cleanLink($tag, $attributeName);
            if ($src != null) {
                /* @var DOMAttr $attributeNode */ $attributeNode = $tag->getAttributeNode($attributeName);
                if (!$this->deferUpdate($src, $attributeNode)) {
                    /* @var String $ns */ $ns = TranslateUtil::normalizePlainText($src)->toString();
                    /* @var JList|TranslationEntry[] $entries */ $entries = $this->normalizedLookup->get($ns);
                    if ($entries != null) {
                        /* @var String $translation */ $translation = null;
                        foreach($entries as $e) {
                            /* @var String $t */ $t = $e->getTarget();
                            if ((strcmp($e->getSource(), $src) == 0) && !$e->isTargetEmpty()) {
                                $translation = $t;
                                break;
                            }
                            if ($t != null) $translation = $t;
                        }
                        ++$this->entriesOnPage;
                        if ($translation != null) {
                            ++$this->entriesWithTranslation;
                            $attributeNode->nodeValue = ($translation);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param DOMNode $n
     * @return boolean
     */
    private function isSwapElement($n)
    {
        if ($n->nodeType == Node::ELEMENT_NODE) {
            /* @var DOMElement $ne */ $ne = /*DOMElement*/ $n;
            /* @var String $elementClass */ $elementClass = $ne->getAttribute("class");
            return ($elementClass != null && $this->projectConfig->getSwapElementPattern()->matcher($elementClass)->find());
        }
        return false;
    }

    /**
     * @param DOMElement $e
     * @param boolean $translateContent
     * @return void
     */
    private function translate($e, $translateContent)
    {
        if (!$this->shouldProcessElement($e, $this->noTranslateClassMatcher)) return;
        for (/* @var DOMNode $n */ $n = $e->firstChild; $n != null; $n = $n->nextSibling) {
            if ($n->nodeType == Node::TEXT_NODE) {
                if ($translateContent) $this->processTranslatableNode($n);
            } else if ($n->nodeType == Node::ELEMENT_NODE && $this->shouldProcessElement(/*DOMElement*/ $n, $this->noTranslateClassMatcher)) {
                /* @var TranslationEntry $transform */ $transform = null;
                /* @var DOMElement $root */ $root = null;
                /* @var IntToNodeMap $nodeMap */ $nodeMap = null;
                /* @var boolean $translateSubContent */ $translateSubContent = $translateContent;
                /* @var DOMElement $ne */ $ne = /*DOMElement*/ $n;
                /* @var DOMAttr $title */ $title = $ne->getAttributeNode("title");
                if ($title != null) {
                    $this->processTranslatableNode($title);
                }
                /* @var String $name */ $name = CompatibleString::getStr($ne->nodeName)->toLowerCase();
                if ((strcmp($name, "meta") == 0)) {
                    /* @var String $metaName */ $metaName = $ne->getAttribute("name");
                    if ((strcmp($metaName, "") == 0)) {
                        $metaName = $ne->getAttribute("property");
                    }
                    /* @var DOMAttr $contentAttr */ $contentAttr = $ne->getAttributeNode("content");
                    if ($metaName != null && $contentAttr != null && $this->projectConfig->getTranslatedMetas()->contains(CompatibleString::getStr($metaName)->toLowerCase())) {
                        $this->processTranslatableNode($contentAttr);
                    }
                }
                if ($this->isSwapElement($ne)) {
                    /* @var DOMAttr $id */ $id = $ne->getAttributeNode("id");
                    if ($id != null) {
                        /* @var String $nt */ $nt = TranslateUtil::normalizePlainText($id->nodeValue)->toString();
                        /* @var TranslationEntry $match */ $match = $this->swapEntries->get($nt);
                        ++$this->entriesOnPage;
                        if ($match != null) {
                            $this->assignRecordToNode($id, $match, null);
                            if ($match->isTargetEmpty()) $this->handledMissingNodes->add($id); else ++$this->entriesWithTranslation;
                            $this->changeBlock($ne, $match);
                        } else {
                            $this->missingNodes->add($id);
                        }
                    }
                    continue;
                }
                if ($this->projectConfig->getBlockElements()->contains($name)) {
                    $nodeMap = new IntToNodeMap();
                    $this->idToHTMLNode = $nodeMap;
                    $root = $this->blockXMLBuilder->buildBlockXML($ne);
                    $this->blockXMLBuilder->mergeNodes($this->handledMissingNodes, $this->idToHTMLNode);
                    $this->resetXMLWithRoot($root);
                    $this->idToHTMLNode = null;
                    /* @var String $path */ $path = $this->pathBuilder->nodePath($ne);
                    /* @var String $normalized */ $normalized = $this->projectConfig->getInvalidCharacterMatcher()->removeFrom(TranslateUtil::normalizeXML($this->serializer, $this->xml)->toString());
                    /* @var JList|TranslationEntry[] $matches */ $matches = $this->normalizedLookup->get($normalized);
                    if ($matches != null) {
                        /* @var int $similarity */ $similarity = -1;
                        /* @var TranslationEntry $selected */ $selected = null;
                        foreach($matches as $te) {
                            /* @var int $matchLen */ $matchLen = $this->prefixMatchLength($path, $te->getPath());
                            if ($matchLen > $similarity && $te->isBlock() && $te->isCompatibleWith($root->firstChild) && ($selected == null || $selected->getTarget() == null || $te->getTarget() != null)) {
                                $similarity = $matchLen;
                                $selected = $te;
                            }
                        }
                        if ($selected != null) {
                            $translateSubContent = $selected->getTarget() == null;
                            $transform = $selected;
                            if ($selected->getTarget() == null) $this->handledMissingNodes->add($ne);
                            $this->assignRecordToNode($ne, $transform, $nodeMap);
                        } else {
                            $translateSubContent = true;
                            $this->assignNodeMapToNode($ne, $nodeMap);
                        }
                    } else {
                        $translateSubContent = true;
                        $this->assignNodeMapToNode($ne, $nodeMap);
                    }
                    $this->blockToBlockXML->put($ne, $root);
                    $this->resetXML();
                }
                if ((strcmp($name, "input") == 0)) {
                    /* @var String $type */ $type = $ne->getAttribute("type");
                    if ($type != null) $type = CompatibleString::getStr($type)->toLowerCase();
                    if ($this->projectConfig->getTranslatedInputs()->contains($type)) {
                        /* @var DOMAttr $a */ $a = $ne->getAttributeNode("value");
                        if ($a != null) $this->processTranslatableNode($a);
                    }
                    if ($ne->hasAttribute("placeholder")) {
                        $this->processTranslatableNode($ne->getAttributeNode("placeholder"));
                    }
                }
                if ((strcmp($name, "textarea") == 0)) {
                    if ($ne->hasAttribute("placeholder")) {
                        $this->processTranslatableNode($ne->getAttributeNode("placeholder"));
                    }
                }
                if ((strcmp($name, "img") == 0) || (strcmp($name, "area") == 0) || (strcmp($name, "input") == 0)) {
                    /* @var DOMAttr $alt */ $alt = $ne->getAttributeNode("alt");
                    if ($alt != null) $this->processTranslatableNode($alt);
                }
                if ($this->shouldProcessContent($ne)) {
                    $this->translate($ne, $translateSubContent);
                }
                if ($transform != null) ++$this->entriesOnPage;
                if ($transform != null && $transform->getTarget() != null && !$transform->isTargetEmpty()) {
                    $this->resetXMLWithRoot($root);
                    $this->idToHTMLNode = $nodeMap;
                    $this->translateBlock($ne, $transform, $this->xml);
                    $this->idToHTMLNode = null;
                    ++$this->entriesWithTranslation;
                }
            }
        }
    }

    /**
     * @param DOMElement $block
     * @param TranslationEntry $transform
     * @param DOMDocument $xml
     * @return void
     */
    private function translateBlock($block, $transform, $xml)
    {
        /* @var DOMElement $xmlRoot */ $xmlRoot = /*DOMElement*/ $xml->documentElement->firstChild;
        /* @var DOMElement $translationRoot */ $translationRoot = /*DOMElement*/ $transform->getTargetAsBlock()->documentElement->firstChild;
        if ($translationRoot != null) {
            PTMAssert( ((strcmp($xmlRoot->getAttribute("id"), $translationRoot->getAttribute("id")) == 0)) ,  /*__Assertion*/ "id is not correct");
            $xmlRoot->setAttribute("key", $transform->getKeyName());
            if (!$this->justScan) $this->transformBlock($block, $translationRoot);
        }
        $this->handledMissingNodes->add($block);
    }

    /**
     * @param DOMElement $block
     * @param DOMElement $translationRoot
     * @return void
     */
    public function transformBlock($block, $translationRoot)
    {
        if ((strcmp($translationRoot->getAttribute("preserveBreaks"), "true") == 0)) {
            /* @var String $style */ $style = $block->getAttribute("style");
            if ($style == null) {
                $block->setAttribute("style", "white-space: pre;");
            } else {
                /* @var Matcher $pre */ $pre = Translator::$CSS_WHITE_SPACE_REPLACE->matcher($style);
                if ($pre->find()) {
                    $style = $pre->replaceAll("white-space: pre;");
                } else {
                    $style = $this->projectConfig->getTrailingSpacesPattern()->matcher($style)->replaceAll("");
                    if (CompatibleString::getStr($style)->length() > 0 && CompatibleString::getStr($style)->charAt(CompatibleString::getStr($style)->length() - 1) != ';') $style = CompatibleString::getStr($style)->concat("; white-space: pre;"); else $style = CompatibleString::getStr($style)->concat(" white-space: pre;");
                    $block->setAttribute("style", $style);
                }
            }
        }
        /* @var IntToNodeMap $nodeMap */ $nodeMap = $this->idToHTMLNode;
        if ($nodeMap == null) return;
        /* @var DOMNode $firstNode */ $firstNode = $block->firstChild;
        for (/* @var DOMNode $n */ $n = $translationRoot->firstChild; $n != null; $n = $n->nextSibling) {
            if ($n->nodeType == Node::TEXT_NODE) {
                if ($firstNode != null && $firstNode->nodeType == Node::TEXT_NODE) {
                    $firstNode->nodeValue = ($n->nodeValue);
                    $firstNode = $firstNode->nextSibling;
                } else {
                    $block->insertBefore($block->ownerDocument->createTextNode($n->nodeValue), $firstNode);
                }
            } else if ($n->nodeType == Node::ELEMENT_NODE) {
                /* @var DOMElement $ne */ $ne = /*DOMElement*/ $n;
                /* @var String $id */ $id = CompatibleString::getStr($ne->getAttribute("id"))->substring(1);
                /* @var DOMNode $htmlNode */ $htmlNode = $nodeMap->get(parseInt($id));
                if ($htmlNode != $firstNode) {
                    if ($htmlNode->parentNode != null) $htmlNode->parentNode->removeChild($htmlNode);
                    $block->insertBefore($htmlNode, $firstNode);
                }
                if ($htmlNode->nodeType == Node::ELEMENT_NODE && (strcmp($n->nodeName, "g") == 0)) $this->transformBlock(/*DOMElement*/ $htmlNode, $ne);
                $firstNode = $htmlNode->nextSibling;
            }
        }
        while ($firstNode != null) {
            /* @var DOMNode $n */ $n = $firstNode->nextSibling;
            if ($firstNode->nodeType != Node::ELEMENT_NODE || $this->shouldProcessElement(/*DOMElement*/ $firstNode, $this->noTranslateClassMatcher)) $block->removeChild($firstNode);
            $firstNode = $n;
        }
    }

    /**
     * @param DOMElement $e
     * @return boolean
     */
    public static function shouldProcessContent($e)
    {
        /* @var String $nodeName */ $nodeName = CompatibleString::getStr($e->nodeName)->toLowerCase();
        if (WebProxy::$IGNORED_TAG_CONTENT->contains($nodeName)) return false;
        return true;
    }

    /**
     * @param DOMElement $e
     * @param Pattern $noTranslateClassMatcher
     * @return boolean
     */
    public static function shouldProcessElement($e, $noTranslateClassMatcher)
    {
        /* @var String $nodeName */ $nodeName = CompatibleString::getStr($e->nodeName)->toLowerCase();
        if (WebProxy::$IGNORED_TAGS->contains($nodeName)) return false;
        if ((strcmp($e->getAttribute("translate"), "no") == 0)) return false;
        if ($noTranslateClassMatcher != null) {
            /* @var String $className */ $className = $e->getAttribute("class");
            if ($noTranslateClassMatcher->matcher($className)->find()) return false;
        }
        return true;
    }

    /**
     * @param DOMNode $n
     * @return void
     */
    private function processTranslatableNode($n)
    {
        /* @var String $path */ $path = $this->pathBuilder->nodePath($n);
        /* @var String $nt */ $nt = TranslateUtil::normalizePlainText($n->nodeValue)->toString();
        /* @var boolean $textNode */ $textNode = $n->nodeType == Node::TEXT_NODE;
        /* @var JList|TranslationEntry[] $matches */ $matches = $this->normalizedLookup->get($nt);
        if ($matches != null) {
            /* @var int $similarity */ $similarity = -1;
            /* @var TranslationEntry $selected */ $selected = null;
            foreach($matches as $te) {
                /* @var int $matchLen */ $matchLen = $this->prefixMatchLength($path, $te->getPath());
                if ($matchLen > $similarity && !$te->isBlock()) {
                    $similarity = $matchLen;
                    $selected = $te;
                }
            }
            if ($selected != null) {
                if (!$textNode) {
                    ++$this->entriesOnPage;
                    if (!$selected->isTargetEmpty()) ++$this->entriesWithTranslation;
                }
                $this->applyPlainTranslation($n, $selected);
            } else {
                $this->missingNodes->add($n);
            }
        } else {
            if (!$textNode) ++$this->entriesOnPage;
            $this->missingNodes->add($n);
        }
    }

    /**
     * @param DOMNode $n
     * @param TranslationEntry $selected
     * @return void
     */
    private function applyPlainTranslation($n, $selected)
    {
        if (!$selected->isTargetEmpty() && !$this->justScan) {
            /* @var String $t */ $t = $selected->getTarget();
            $n->nodeValue = ($this->adjustSpaces($t, $selected->getSource(), $n->nodeValue));
        }
        $this->assignRecordToNode($n, $selected, null);
    }

    /**
     * @return void
     */
    private function handleMissing()
    {
        /* @var JSArray<HtmlMissingTranslation> $nodesToExport */ $nodesToExport = new JSArray();
        foreach($this->missingNodes as $n) {
            if ($this->handledMissingNodes->contains($n)) continue;
            if ($n->nodeType == Node::TEXT_NODE) {
                /* @var DOMNode $p */ $p = $n->parentNode;
                while ($p != null && $p->nodeType == Node::ELEMENT_NODE && !$this->projectConfig->getBlockElements()->contains(CompatibleString::getStr($p->nodeName)->toLowerCase())) $p = $p->parentNode;
                if ($p != null && $p->nodeType == Node::ELEMENT_NODE) {
                    if ($this->handledMissingNodes->contains($p)) continue;
                    $this->handledMissingNodes->add($p);
                    /* @var DOMElement $root */ $root = /*DOMElement*/ $this->blockToBlockXML->get($p);
                    $this->resetXMLWithRoot($root);
                    /* @var String $inner */ $inner = $this->serializer->serializeText($this->xml);
                    if (!$this->projectConfig->getWhiteSpacePattern()->matcher($inner)->matches() && !$this->projectConfig->getDontTranslatePattern()->matcher($inner)->matches()) {
                        /* @var HtmlMissingTranslation $missing */ $missing = new HtmlMissingTranslation();
                        $missing->setOriginal($this->serializer->serializeContents($root));
                        $missing->setPath($this->pathBuilder->nodePath($p));
                        $missing->setHtmlNode($p);
                        $nodesToExport->push($missing);
                        $this->xml->removeChild($root);
                        $this->xml->appendChild($this->xml->createElement("block"));
                    }
                } else {
                    /* @var HtmlMissingTranslation $missingText */ $missingText = $this->nodeValueTranslation($n);
                    if ($missingText != null) $nodesToExport->push($missingText);
                }
            } else if ($n->nodeType == Node::ATTRIBUTE_NODE) {
                /* @var HtmlMissingTranslation $missingAttribute */ $missingAttribute = $this->attributeNodeTranslation(/*DOMAttr*/ $n);
                if ($missingAttribute != null) $nodesToExport->push($missingAttribute);
            }
        }
        $this->storeMissing($nodesToExport);
    }

    /**
     * @param String $a
     * @param String $b
     * @return int
     */
    public static function prefixMatchLength($a, $b)
    {
        /* @var int $l */ $l = Math::min(CompatibleString::getStr($a)->length(), CompatibleString::getStr($b)->length());
        for (/* @var int $i */ $i = 0; $i < $l; $i++) if (CompatibleString::getStr($a)->charAt($i) != CompatibleString::getStr($b)->charAt($i)) return $i;
        return $l;
    }

    /**
     * @param String $s
     * @param String $o
     * @param String $doc
     * @return String
     */
    private function adjustSpaces($s, $o, $doc)
    {
        /* @var Matcher $m */ $m = $this->projectConfig->getLeadingSpacesPattern()->matcher($o); $docM = $this->projectConfig->getLeadingSpacesPattern()->matcher($doc);
        if ($m->find() && $docM->find() && ($m->end() > 0) != ($docM->end() > 0)) {
            $s = CompatibleString::getStr($docM->group())->concat($this->projectConfig->getLeadingSpacesPattern()->matcher($s)->replaceFirst(""));
        }
        $m = $this->projectConfig->getTrailingSpacesPattern()->matcher($o);
        $docM = $this->projectConfig->getTrailingSpacesPattern()->matcher($doc);
        if ($m->find() && $docM->find() && ($m->start() < CompatibleString::getStr($o)->length() - 1) != ($docM->start() < CompatibleString::getStr($doc)->length() - 1)) {
            $s = CompatibleString::getStr($this->projectConfig->getTrailingSpacesPattern()->matcher($s)->replaceFirst(""))->concat($docM->group());
        }
        return $s;
    }

}

Translator::$logger = LoggerFactory::getLogger(Translator::getClassName());
Translator::$noTranslateClasses = Lists::newArrayList("__ptNoTranslate", "SL_hide", "EL_hide");
Translator::$CSS_WHITE_SPACE_REPLACE = Pattern::compile("(?:^|;)\\s*white-space:[^;]*;?");
Translator::$LAST_ELEMENT_NAME = Pattern::compile("/([^%:]+):[-0-9]+/([%#][^%:]+):[-0-9]+$");
Translator::$MANUAL_ENTRY_PATH = Pattern::compile("^manual\\:");
Translator::$SWAP_ELEMENT_ENTRY_PATH = Pattern::compile("^id\\:");

