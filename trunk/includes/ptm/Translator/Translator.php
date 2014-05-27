<?php
class Translator extends TranslatorBase {

    public static function create() { return new Translator(); }

    private /* PathBuilder */ $pathBuilder;
    private /* Map<String, List<TranslationEntry>> */ $normalizedLookup;
    private /* List<TranslationEntry> */ $manualEntries;
    private /* NodeSet */ $missingNodes;
    private /* NodeSet */ $handledMissingNodes;
    private /* Pattern */ $noTranslateClassMatcher;
    private /* boolean */ $markTextNodesAsHandled;
    private /* IntToNodeMap */ $idToHTMLNode;
    private /* NodeMap */ $blockToBlockXML;
    private /* CompatibleXMLSerializer */ $serializer;
    private /* boolean */ $justScan;
    public static /* Pattern */ $LEADING_SPACES;
    public static /* Pattern */ $TRAILING_SPACES;
    public static /* Pattern */ $CSS_WHITE_SPACE_REPLACE;
    public static /* Pattern */ $DONT_TRANSLATE_ISOLATED;
    public static /* Pattern */ $DONT_TRANSLATE;
    public static /* Pattern */ $WHITE_SPACE;
    public static /* Pattern */ $LAST_ELEMENT_NAME;
    public static /* Set<String> */ $BLOCK_ELEMENTS;
    public static /* Set<String> */ $TRANSLATED_METAS;
    public static /* Pattern */ $MANUAL_ENTRY_PATH;
    private /* Document */ $xml;

    public function __construct() {
        $this->missingNodes = new NodeSet();
        $this->handledMissingNodes = new NodeSet();
        $this->noTranslateClassMatcher = EncodeUtil::matcherForClassNames("__ptNoTranslate");
        $this->markTextNodesAsHandled = false;
        $this->idToHTMLNode = null;
        $this->blockToBlockXML = new NodeMap();
        $this->serializer = new CompatibleXMLSerializer();
        $this->justScan = false;
    }


    public function /* void */ setNormalizedLookup(/*Map<String, List<TranslationEntry>>*/ $nl)
    {
        $this->normalizedLookup = $nl;
    }

    public function /* void */ setManualEntries(/*List<TranslationEntry>*/ $entries)
    {
        $this->manualEntries = $entries;
    }

    public function /* void */ setIdToHTMLNode(/*IntToNodeMap*/ $mapping)
    {
        $this->idToHTMLNode = $mapping;
    }

    public function /* void */ translateDocument(/*Document*/ $doc)
    {
        $this->pathBuilder = new PathBuilder($doc);
        $this->xml = XMLUtil::createDocument();
        $this->resetXML();
        $this->translate($doc->documentElement, true);
        $this->swapLinks($doc);
        $this->injectManuals($doc);
        $this->handleMissing();
    }

    private function /* void */ resetXML()
    {
        $this->resetXMLWithRoot($this->xml->createElement("block"));
    }

    private function /* void */ resetXMLWithRoot(/*Element*/ $newRoot)
    {
        if ($this->xml->firstChild != null) $this->xml->removeChild($this->xml->firstChild);
        $this->xml->appendChild($newRoot);
    }

    private function /* void */ injectManuals(/*Document*/ $doc)
    {
        if ($this->justScan) return;
        /*StringSet*/ $translated = new StringSet();
        foreach($this->manualEntries as /*TranslationEntry*/ $te) {
            /*String*/ $normalSource = $te->getNormalizedSource()->toString();
            /*Matcher*/ $m = Translator::$LAST_ELEMENT_NAME->matcher($te->getPath());
            if ($m->find()) {
                /*Node*/ $match = null;
                /*int*/ $matchLen = -1;
                /*String*/ $matchPath = null;
                /*String*/ $nodeName = $m->group(1);
                /*String*/ $attributeName = $m->group(2);
                /*boolean*/ $textNode = false;
                if (CompatibleString::getStr($attributeName)->charAt(0) == '%') {
                    $attributeName = CompatibleString::getStr($attributeName)->substring(1);
                } else {
                    $textNode = true;
                }
                /*NodeList*/ $elements = $doc->getElementsByTagName($nodeName);
                /*int*/ $l = $elements->length;
                for (/*int*/ $i = 0; $i < $l; ++$i) {
                    /*Element*/ $e = /*Element*/ $elements->item($i);
                    /*Node*/ $attr = null;
                    if ($textNode) {
                        $attr = $e->firstChild;
                    } else {
                        $attr = $e->getAttributeNode($attributeName);
                    }
                    if ($attr == null) continue;
                    /*String*/ $path = $this->pathBuilder->nodePath($attr);
                    if ($translated->contains($path)) continue;
                    /*int*/ $thisMatchLen = $this->prefixMatchLength(CompatibleString::getStr("manual:-1/")->concat($path), $te->getPath());
                    if ((strcmp($normalSource, TranslateUtil::normalizePlainText($attr->nodeValue)->toString()) == 0) && $matchLen < $thisMatchLen) {
                        $match = $attr;
                        $matchLen = $thisMatchLen;
                        $matchPath = $path;
                    }
                }
                if ($match != null) {
                    $this->assignRecordToNode($match, $te, null);
                    if ($te->getTarget() != null) $match->nodeValue = ($te->getTarget());
                    $translated->add($matchPath);
                }
            }
        }
    }

    private function /* void */ swapLinks(/*Document*/ $doc)
    {
        if ($this->justScan) return;
        $this->swapResources($doc, "img", "src");
        $this->swapResources($doc, "a", "href");
    }

    private function /* void */ swapResources(/*Document*/ $doc, /*String*/ $tagName, /*String*/ $attributeName)
    {
        /*NodeList*/ $tags = $doc->getElementsByTagName($tagName);
        for (/*int*/ $i = 0, $l = $tags->length; $i < $l; ++$i) {
            /*Element*/ $tag = /*Element*/ $tags->item($i);
            /*String*/ $src = $this->cleanLink($tag, $attributeName);
            if ($src != null) {
                /*String*/ $ns = TranslateUtil::normalizePlainText($src)->toString();
                /*List<TranslationEntry>*/ $entries = $this->normalizedLookup->get($ns);
                if ($entries != null) {
                    /*String*/ $translation = null;
                    foreach($entries as /*TranslationEntry*/ $e) {
                        /*String*/ $t = $e->getTarget();
                        if ((strcmp($e->getSource(), $src) == 0) && $t != null) {
                            $translation = $t;
                            break;
                        }
                        if ($t != null) $translation = $t;
                    }
                    if ($translation != null) $tag->setAttribute($attributeName, $translation);
                }
            }
        }
    }

    private function /* void */ translate(/*Element*/ $e, /*boolean*/ $translateContent)
    {
        if (!$this->shouldProcessElement($e)) return;
        for (/*Node*/ $n = $e->firstChild; $n != null; $n = $n->nextSibling) {
            if ($n->nodeType == Node::TEXT_NODE) {
                if ($translateContent) $this->processTranslatableNode($n);
            } else if ($n->nodeType == Node::ELEMENT_NODE && $this->shouldProcessElement(/*Element*/ $n)) {
                /*TranslationEntry*/ $transform = null;
                /*Element*/ $root = null;
                /*IntToNodeMap*/ $nodeMap = null;
                /*boolean*/ $translateSubContent = $translateContent;
                /*Element*/ $ne = /*Element*/ $n;
                /*Attr*/ $title = $ne->getAttributeNode("title");
                if ($title != null) {
                    $this->processTranslatableNode($title);
                }
                /*String*/ $name = CompatibleString::getStr($ne->nodeName)->toLowerCase();
                if ((strcmp($name, "meta") == 0)) {
                    /*String*/ $metaName = $ne->getAttribute("name");
                    /*Attr*/ $contentAttr = $ne->getAttributeNode("content");
                    if ($metaName != null && $contentAttr != null && Translator::$TRANSLATED_METAS->contains(CompatibleString::getStr($metaName)->toLowerCase())) {
                        $this->processTranslatableNode($contentAttr);
                    }
                }
                if (Translator::$BLOCK_ELEMENTS->contains($name)) {
                    $nodeMap = new IntToNodeMap();
                    $this->idToHTMLNode = $nodeMap;
                    $root = $this->xml->documentElement;
                    $this->clear($root);
                    $this->buildBlockXML($ne, $root);
                    $this->idToHTMLNode = null;
                    /*String*/ $path = $this->pathBuilder->nodePath($ne);
                    /*String*/ $normalized = TranslateUtil::normalizeXML($this->serializer, $this->xml)->toString();
                    /*List<TranslationEntry>*/ $matches = $this->normalizedLookup->get($normalized);
                    if ($matches != null) {
                        /*int*/ $similarity = -1;
                        /*TranslationEntry*/ $selected = null;
                        foreach($matches as /*TranslationEntry*/ $te) {
                            /*int*/ $matchLen = $this->prefixMatchLength($path, $te->getPath());
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
                    /*String*/ $type = $ne->getAttribute("type");
                    if ($type != null) $type = CompatibleString::getStr($type)->toLowerCase();
                    if ((strcmp($type, "button") == 0) || (strcmp($type, "submit") == 0)) {
                        /*Attr*/ $a = $ne->getAttributeNode("value");
                        if ($a != null) $this->processTranslatableNode($a);
                    }
                    if ($ne->hasAttribute("placeholder")) {
                        $this->processTranslatableNode($ne->getAttributeNode("placeholder"));
                    }
                }
                if ((strcmp($name, "img") == 0)) {
                    /*Attr*/ $alt = $ne->getAttributeNode("alt");
                    if ($alt != null) $this->processTranslatableNode($alt);
                }
                $this->translate($ne, $translateSubContent);
                if ($transform != null && $transform->getTarget() != null) {
                    $this->resetXMLWithRoot($root);
                    $this->idToHTMLNode = $nodeMap;
                    $this->translateBlock($ne, $transform, $this->xml);
                    $this->idToHTMLNode = null;
                }
            }
        }
    }

    private function /* void */ translateBlock(/*Element*/ $block, /*TranslationEntry*/ $transform, /*Document*/ $xml)
    {
        /*Element*/ $xmlRoot = /*Element*/ $this->xml->documentElement->firstChild;
        /*Document*/ $translation = XMLUtil::createDocument(StringBuilder::create("<?xml version=\"1.0\" encoding=\"UTF-8\"?><block>")->append($transform->getTarget())->append("</block>")->toString());
        /*Element*/ $translationRoot = /*Element*/ $translation->documentElement->firstChild;
        if ($translationRoot != null) {
            PTMAssert( ((strcmp($xmlRoot->getAttribute("id"), $translationRoot->getAttribute("id")) == 0)) ,  /*__Assertion*/ "id is not correct");
            if (!$this->justScan) $this->transformBlock($block, $translationRoot);
        }
        $this->handledMissingNodes->add($block);
    }

    public function /* void */ transformBlock(/*Element*/ $block, /*Element*/ $translationRoot)
    {
        if ((strcmp($translationRoot->getAttribute("preserveBreaks"), "true") == 0)) {
            /*String*/ $style = $block->getAttribute("style");
            if ($style == null) {
                $block->setAttribute("style", "white-space: pre;");
            } else {
                /*Matcher*/ $pre = Translator::$CSS_WHITE_SPACE_REPLACE->matcher($style);
                if ($pre->find()) {
                    $style = $pre->replaceAll("white-space: pre;");
                } else {
                    $style = Translator::$TRAILING_SPACES->matcher($style)->replaceAll("");
                    if (CompatibleString::getStr($style)->length() > 0 && CompatibleString::getStr($style)->charAt(CompatibleString::getStr($style)->length() - 1) != ';') $style = CompatibleString::getStr($style)->concat("; white-space: pre;"); else $style = CompatibleString::getStr($style)->concat(" white-space: pre;");
                    $block->setAttribute("style", $style);
                }
            }
        }
        /*IntToNodeMap*/ $nodeMap = $this->idToHTMLNode;
        if ($nodeMap == null) return;
        /*Node*/ $firstNode = $block->firstChild;
        for (/*Node*/ $n = $translationRoot->firstChild; $n != null; $n = $n->nextSibling) {
            if ($n->nodeType == Node::TEXT_NODE) {
                if ($firstNode != null && $firstNode->nodeType == Node::TEXT_NODE) {
                    $firstNode->nodeValue = ($n->nodeValue);
                    $firstNode = $firstNode->nextSibling;
                } else {
                    $block->insertBefore($block->ownerDocument->createTextNode($n->nodeValue), $firstNode);
                }
            } else if ($n->nodeType == Node::ELEMENT_NODE) {
                /*Element*/ $ne = /*Element*/ $n;
                /*String*/ $id = $ne->getAttribute("id");
                /*Node*/ $htmlNode = $nodeMap->get(parseInt(CompatibleString::getStr($id)->substring(1)));
                if ($htmlNode != $firstNode) {
                    if ($htmlNode->parentNode != null) $htmlNode->parentNode->removeChild($htmlNode);
                    $block->insertBefore($htmlNode, $firstNode);
                }
                if ($htmlNode->nodeType == Node::ELEMENT_NODE && (strcmp($n->nodeName, "g") == 0)) $this->transformBlock(/*Element*/ $htmlNode, $ne);
                $firstNode = $htmlNode->nextSibling;
            }
        }
        while ($firstNode != null) {
            /*Node*/ $n = $firstNode->nextSibling;
            if ($firstNode->nodeType != Node::ELEMENT_NODE || $this->shouldProcessElement(/*Element*/ $firstNode)) $block->removeChild($firstNode);
            $firstNode = $n;
        }
    }

    private function /* boolean */ shouldProcessElement(/*Element*/ $e)
    {
        /*String*/ $nodeName = CompatibleString::getStr($e->nodeName)->toLowerCase();
        if (WebProxy::$IGNORED_TAGS->contains($nodeName)) return false;
        if ($this->noTranslateClassMatcher != null) {
            /*String*/ $className = $e->getAttribute("class");
            if ($this->noTranslateClassMatcher->matcher($className)->find()) return false;
        }
        return true;
    }

    private function /* void */ processTranslatableNode(/*Node*/ $n)
    {
        /*String*/ $path = $this->pathBuilder->nodePath($n);
        /*String*/ $nodeValue = $n->nodeValue;
        /*String*/ $nt = TranslateUtil::normalizePlainText($nodeValue)->toString();
        /*List<TranslationEntry>*/ $matches = $this->normalizedLookup->get($nt);
        if ($matches != null) {
            /*int*/ $similarity = -1;
            /*TranslationEntry*/ $selected = null;
            foreach($matches as /*TranslationEntry*/ $te) {
                /*int*/ $matchLen = $this->prefixMatchLength($path, $te->getPath());
                if ($matchLen > $similarity && !$te->isBlock()) {
                    $similarity = $matchLen;
                    $selected = $te;
                }
            }
            if ($selected != null) {
                /*String*/ $t = $selected->getTarget();
                if ($t != null && !$this->justScan) $n->nodeValue = ($this->adjustSpaces($t, $selected->getSource(), $nodeValue));
                $this->assignRecordToNode($n, $selected, null);
            } else {
                $this->missingNodes->add($n);
            }
        } else {
            $this->missingNodes->add($n);
        }
    }

    private function /* void */ handleMissing()
    {
        /*JSArray<MissingTranslation>*/ $nodesToExport = new JSArray();
        $this->markTextNodesAsHandled = true;
        foreach($this->missingNodes as /*Node*/ $n) {
            if ($this->handledMissingNodes->contains($n)) continue;
            if ($n->nodeType == Node::TEXT_NODE) {
                /*Node*/ $p = $n->parentNode;
                while ($p != null && $p->nodeType == Node::ELEMENT_NODE && !Translator::$BLOCK_ELEMENTS->contains(CompatibleString::getStr($p->nodeName)->toLowerCase())) $p = $p->parentNode;
                if ($p != null && $p->nodeType == Node::ELEMENT_NODE) {
                    if ($this->handledMissingNodes->contains($p)) continue;
                    $this->handledMissingNodes->add($p);
                    /*Element*/ $root = /*Element*/ $this->blockToBlockXML->get($p);
                    $this->resetXMLWithRoot($root);
                    /*String*/ $inner = $this->serializer->serializeText($this->xml);
                    if (!Translator::$WHITE_SPACE->matcher($inner)->matches() && !Translator::$DONT_TRANSLATE->matcher($inner)->matches()) {
                        /*MissingTranslation*/ $missing = new MissingTranslation();
                        $missing->setOriginal($this->serializer->serializeContents($root));
                        $missing->setPath($this->pathBuilder->nodePath($p));
                        $missing->setHtmlNode($p);
                        $nodesToExport->push($missing);
                        $this->xml->removeChild($root);
                        $this->xml->appendChild($this->xml->createElement("block"));
                    }
                } else {
                    /*MissingTranslation*/ $missingText = $this->nodeValueTranslation($n);
                    if ($missingText != null) $nodesToExport->push($missingText);
                }
            } else if ($n->nodeType == Node::ATTRIBUTE_NODE) {
                /*MissingTranslation*/ $missingAttribute = $this->nodeValueTranslation($n);
                if ($missingAttribute != null) $nodesToExport->push($missingAttribute);
            }
        }
        $this->storeMissing($nodesToExport);
    }

    private function /* MissingTranslation */ nodeValueTranslation(/*Node*/ $n)
    {
        if (Translator::$DONT_TRANSLATE_ISOLATED->matcher($n->nodeValue)->matches()) return null;
        /*MissingTranslation*/ $missing = new MissingTranslation();
        $missing->setOriginal($n->nodeValue);
        $missing->setPath($this->pathBuilder->nodePath($n));
        $missing->setHtmlNode($n);
        return $missing;
    }

    private function /* void */ buildBlockXML(/*Element*/ $htmlNode, /*Element*/ $xmlNode)
    {
        $this->buildBlockXMLFromIndex($htmlNode, $xmlNode, 0);
    }

    private function /* int */ buildBlockXMLFromIndex(/*Element*/ $htmlNode, /*Element*/ $xmlParent, /*int*/ $idIndex)
    {
        if (!$this->shouldProcessElement($htmlNode)) {
            $this->appendNodeWithIdAndAttrs($xmlParent, $htmlNode, "x", $idIndex++, "ctype", "x-placeholder");
            return $idIndex;
        }
        if ($this->markTextNodesAsHandled) {
            for (/*Node*/ $n = $htmlNode->firstChild; $n != null; $n = $n->nextSibling) {
                if ($n->nodeType == Node::TEXT_NODE) $this->handledMissingNodes->add($n);
            }
        }
        /*boolean*/ $translatable = false;
        for (/*Node*/ $n = $htmlNode->firstChild; $n != null; $n = $n->nextSibling) {
            if ($n->nodeType == Node::ELEMENT_NODE && $this->shouldProcessElement(/*Element*/ $n)) {
                $translatable = true;
                break;
            } else if ($n->nodeType == Node::TEXT_NODE) {
                if (!Translator::$DONT_TRANSLATE->matcher($n->nodeValue)->matches()) {
                    $translatable = true;
                    break;
                }
            }
        }
        if (!$translatable) {
            $this->appendNodeWithIdAndAttrs($xmlParent, $htmlNode, "x", $idIndex++, "ctype", "x-prune");
            return $idIndex;
        }
        /*int*/ $thisIndex = $idIndex++;
        /*Element*/ $parent = $this->appendNodeWithId($xmlParent, $htmlNode, "g", $thisIndex);
        for (/*Node*/ $n = $htmlNode->firstChild; $n != null; $n = $n->nextSibling) {
            if ($n->nodeType == Node::ELEMENT_NODE) {
                if (Translator::$BLOCK_ELEMENTS->contains(CompatibleString::getStr($n->nodeName)->toLowerCase())) $this->appendNodeWithIdAndAttrs($parent, $n, "x", $idIndex++, "ctype", "x-block"); else $idIndex = $this->buildBlockXMLFromIndex(/*Element*/ $n, $parent, $idIndex);
            } else if ($n->nodeType == Node::TEXT_NODE) {
                /*String*/ $content = $n->nodeValue;
                if (Translator::$DONT_TRANSLATE->matcher($content)->matches()) $this->appendNodeWithIdAndAttrs($parent, $n, "x", $idIndex++, "ctype", "x-number"); else $parent->appendChild($xmlParent->ownerDocument->createTextNode($content));
            }
        }
        $translatable = false;
        for (/*Node*/ $n = $parent->firstChild; $n != null; $n = $n->nextSibling) {
            if ($n->nodeType == Node::TEXT_NODE || !(strcmp($n->nodeName, "x") == 0)) {
                $translatable = true;
                break;
            }
        }
        if ($translatable) {
            return $idIndex;
        } else {
            $xmlParent->removeChild($parent);
            if ($this->idToHTMLNode != null) $this->idToHTMLNode->clearRange($thisIndex, $idIndex);
            $this->appendNodeWithId($xmlParent, $htmlNode, "x", $thisIndex++);
            return $thisIndex;
        }
    }

    private function /* Element */ appendNodeWithId(/*Element*/ $parentNode, /*Node*/ $htmlRelatedNode, /*String*/ $nodeName, /*int*/ $idIndex)
    {
        return $this->appendNodeWithIdAndAttrs($parentNode, $htmlRelatedNode, $nodeName, $idIndex, /*String[]*/ null);
    }

    private function /* Element */ appendNodeWithIdAndAttrs(/*Element*/ $parentNode, /*Node*/ $htmlRelatedNode, /*String*/ $nodeName, /*int*/ $idIndex/*, String...*/ )
    {
        $attrs = array_slice(func_get_args(), 4);
        /*Element*/ $e = $parentNode->ownerDocument->createElement($nodeName);
        $e->setAttribute("id", StringBuilder::create("_")->append($idIndex)->toString());
        if ($attrs != null && $attrs[0] != null) {
            for (/*int*/ $i = 0; $i < sizeof($attrs); $i += 2) $e->setAttribute($attrs[$i], $attrs[$i + 1]);
        }
        $parentNode->appendChild($e);
        if ($this->idToHTMLNode != null) $this->idToHTMLNode->put($idIndex, $htmlRelatedNode);
        return $e;
    }

    private function /* void */ clear(/*Element*/ $element)
    {
        while ($element->firstChild != null) $element->removeChild($element->firstChild);
    }

    private function /* int */ prefixMatchLength(/*String*/ $a, /*String*/ $b)
    {
        /*int*/ $l = Math::min(CompatibleString::getStr($a)->length(), CompatibleString::getStr($b)->length());
        for (/*int*/ $i = 0; $i < $l; $i++) if (CompatibleString::getStr($a)->charAt($i) != CompatibleString::getStr($b)->charAt($i)) return $i;
        return $l;
    }

    private function /* String */ adjustSpaces(/*String*/ $s, /*String*/ $o, /*String*/ $doc)
    {
        /*Matcher*/ $m = Translator::$LEADING_SPACES->matcher($o); $docM = Translator::$LEADING_SPACES->matcher($doc);
        if ($m->find() && $docM->find() && ($m->end() > 0) != ($docM->end() > 0)) {
            $s = CompatibleString::getStr($docM->group())->concat(Translator::$LEADING_SPACES->matcher($s)->replaceFirst(""));
        }
        $m = Translator::$TRAILING_SPACES->matcher($o);
        $docM = Translator::$TRAILING_SPACES->matcher($doc);
        if ($m->find() && $docM->find() && ($m->start() < CompatibleString::getStr($o)->length() - 1) != ($docM->start() < CompatibleString::getStr($doc)->length() - 1)) {
            $s = CompatibleString::getStr(Translator::$TRAILING_SPACES->matcher($s)->replaceFirst(""))->concat($docM->group());
        }
        return $s;
    }

}

Translator::$LEADING_SPACES = Pattern::compile("^\\s*");
Translator::$TRAILING_SPACES = Pattern::compile("\\s*$");
Translator::$CSS_WHITE_SPACE_REPLACE = Pattern::compile("(?:^|;)\\s*white-space:[^;]*;?");
Translator::$DONT_TRANSLATE_ISOLATED = Pattern::compile("^[-0-9\\s,\\.\\[\\]]*$");
Translator::$DONT_TRANSLATE = Pattern::compile("^[-\\s,\\.\\[\\]]*[0-9][-0-9\\s,\\.\\[\\]]*$");
Translator::$WHITE_SPACE = Pattern::compile("^\\s*$");
Translator::$LAST_ELEMENT_NAME = Pattern::compile("/([^%:]+):[-0-9]+/([%#][^%:]+):[-0-9]+$");
Translator::$BLOCK_ELEMENTS = immutableTightSet("button", "address", "article", "aside", "audio", "blockquote", "canvas", "dt", "dd", "div", "dl", "fieldset", "figcaption", "figure", "footer", "form", "h1", "h2", "h3", "h4", "h5", "h6", "header", "hgroup", "hr", "noscript", "ol", "output", "p", "pre", "section", "table", "td", "th", "caption", "ul", "li", "video", "body");
Translator::$TRANSLATED_METAS = immutableTightSet("description", "keywords", "author", "copyright", "contact");
Translator::$MANUAL_ENTRY_PATH = Pattern::compile("^manual\\:");

