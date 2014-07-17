<?php
/**
 * User: Atesz
 * Date: 2014.07.07.
 * Time: 16:13
 */

class DOMSearch {

	/**
	 * @param string $text
	 * @return DOMReplacement
	 */
	public static function replaceWithText($text) {
		return new DOMReplacement(null, $text);
	}

	/**
	 * @param DOMNode $node
	 * @return DOMReplacement
	 */
	public static function replaceWithNode($node) {
		return new DOMReplacement($node, null);
	}

	/**
	 * @param DOMElement $rootElement
	 * @param Pattern $search
	 * @param RegexpDOMReplacer $mapping
	 * @throws Exception
	 */
	public static function replaceAll($rootElement, $search, $mapping) {
		$lookup = new XMLAttributedString($rootElement);

		/** @var Matcher $matcher */
		$matcher = $search->matcher($lookup->getText());

		/** @var DOMNode $lastReplaceNode */
		$lastReplaceNode = null;

		$lastReplaceOffset = -1;

		/** @var DOMHitInterval[] $hitIntervals */
		$hitIntervals = array();

		while ($matcher->find()) {
			$groupCount = $matcher->groupCount();
			$currentGroup = $groupCount == 0 ? 0 : 1;

			for ( ; $currentGroup <= $groupCount; $currentGroup++ ) {
				$startIndex = $matcher->start($currentGroup);
				$endIndex = $matcher->end($currentGroup);

				if ($startIndex < 0 || $endIndex < 0) {
					continue;
				}

				$skip = false;

				$currentHitInterval = new DOMHitInterval($startIndex, $endIndex);
				foreach ($hitIntervals as $hi) {
					if ($hi->overlapping($currentHitInterval)) {
						$skip = true;
						break;
					}
				}
				if ($skip) {
					continue;
				}

				$hitIntervals[] = $currentHitInterval;

				$intervals = $lookup->getNodesInRange($startIndex, $endIndex);
				$firstDelta = 0;
				if (count($intervals) == 0)
					continue;

				if ($lastReplaceNode === $intervals[0]->getNode()) {
					$firstDelta = $lastReplaceOffset;
					$lastReplaceNode = null;
				}

				/** @var DOMMatchRange[] $parts */
				$parts = array();
				/** @var DOMDocument $doc */
				$doc = null;

				foreach ($intervals as $iv) {
					$ivs = $iv->getStart();
					$tag = $iv->getNode();
					$tagText = new CompatibleString($tag->nodeValue);
					$rangeStart = max($startIndex-$ivs+$firstDelta, 0);
					$rangeEnd = min($endIndex-$ivs+$firstDelta, $tagText->length());

					if ($doc === null)
						$doc = $tag->ownerDocument;

					if ($rangeStart >= max(1, $tagText->length()))
						throw new Exception("DOMSearch replaceAll error, invalid range 0x0001 (".sprintf("%d, %d, %d", $rangeStart, $tagText->length(), $firstDelta).")");

					if ($rangeEnd < 0)
						throw new Exception("DOMSearch replaceAll error, invalid range 0x0002 (".sprintf("%d, %d", $rangeEnd, $firstDelta).")");

					$parts[] = new DOMMatchRange(
						$tagText->substring($rangeStart, $rangeEnd),
						$tag, $rangeStart, $rangeEnd, $firstDelta
					);
					$firstDelta = 0;
				}

				/** @var string[] $texts */
				$texts = array();

				foreach ($parts as $r) {
					$texts[] = $r->getText();
				}

				$match = new DOMMatch($doc, $currentGroup, $texts, $matcher->getResult());
				$replacement = $mapping->apply($match);

				if (count($replacement) != count($texts))
					throw new Exception("The replacement array must have the same amount of elements");

				for ($pi=0,$len=count($parts);$pi<$len;++$pi) {
					/** @var bool $last */
					$last = $pi >= $len-1;
					/** @var DOMMatchRange $p */
					$p = $parts[$pi];
					/** @var DOMReplacement $r */
					$r = $replacement[$pi];

					$node = $p->getTextNode();
					$nodeText = new CompatibleString($node->nodeValue);
					$replacementText = new CompatibleString($r->getText());

					if (!$replacementText->isEmpty()) {
//					if (!$replacementText->isNull()) {
						$node->nodeValue = $nodeText->substring(0, $p->getRangeStart()).$replacementText->__toString().$nodeText->substring($p->getRangeEnd());
						if ($last) {
							$lastReplaceNode = $node;
							$lastReplaceOffset = $replacementText->length() - ($p->getRangeEnd()-$p->getRangeStart()) + $p->getOffset();
						}
					}
					else {
						$replacementNode = $r->getNode();
						$parentNode = $node->parentNode;
						if ($p->getRangeStart() > 0) {
							$textNode = $node->ownerDocument->createTextNode($nodeText->substring(0, $p->getRangeStart()));
							$parentNode->insertBefore($textNode, $node);
						}

						$parentNode->insertBefore($replacementNode, $node);

						$node->nodeValue = $nodeText->substring($p->getRangeEnd());

						if ($last) {
							$lastReplaceNode = $node;
							$lastReplaceOffset = -$p->getRangeEnd() + $p->getOffset();
						}
					}
				}

			}

		}
	}
} 