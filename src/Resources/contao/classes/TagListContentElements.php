<?php

namespace Hschottm\TagsBundle;

use Contao\Database;
use Contao\System;

/**
 * Class TagListContentElements
 *
 * Provide methods to handle tag input fields.
 * @copyright  Helmut Schottmüller 2010-2024
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    Controller
 */
class TagListContentElements extends TagList
{
	protected $arrContentElements = array();
	protected $arrPages = array();
	protected $inColumn = "";

  public function getRelatedTagList($for_tags, $blnExcludeUnpublishedItems = true)
	{
		$hasBackendUser = System::getContainer()->get('contao.security.token_checker')->hasBackendUser();
		$showUnpublished = System::getContainer()->get('contao.security.token_checker')->isPreviewMode();
		$hasFrontendUser = System::getContainer()->get('contao.security.token_checker')->hasFrontendUser();

		if (!is_array($for_tags)) return array();
		if (!count($this->arrContentElements)) return array();

		$request = System::getContainer()->get('request_stack')->getCurrentRequest();
		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$blnExcludeUnpublishedItems = false;
		}

		$ids = array();
		for ($i = 0; $i < count($for_tags); $i++)
		{
			if (!$hasBackendUser) {
				$arr = Database::getInstance()->prepare("SELECT DISTINCT tl_tag.tid FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND from_table = ?  AND tl_tag.tid IN (" . implode(',', $this->arrContentElements) . ") AND tag = ?" . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND invisible<>1" . " ORDER BY tl_tag.tid ASC")
				->execute('tl_content', $for_tags[$i], time(), time())
				->fetchEach('tid');
			} else {
				$arr = Database::getInstance()->prepare("SELECT DISTINCT tl_tag.tid FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND from_table = ?  AND tl_tag.tid IN (" . implode(',', $this->arrContentElements) . ") AND tag = ?" .  " ORDER BY tl_tag.tid ASC")
				->execute('tl_content', $for_tags[$i])
				->fetchEach('tid');
			}
			if ($i == 0)
			{
				$ids = $arr;
			}
			else
			{
				$ids = array_intersect($ids, $arr);
			}
		}

		$arrCloudTags = array();
		if (count($ids))
		{
			if (!$hasBackendUser) {
				$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND from_table = ?" . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND invisible<>1"  . " AND tl_tag.tid IN (" . implode(",", $ids) . ") GROUP BY tag ORDER BY tag ASC")
				->execute('tl_content', time(), time());
			} else {
				$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND from_table = ?" .  " AND tl_tag.tid IN (" . implode(",", $ids) . ") GROUP BY tag ORDER BY tag ASC")
				->execute('tl_content');
			}
			$list = "";
			$tags = array();
			if ($objTags->numRows)
			{
				while ($objTags->next())
				{
					if (!in_array($objTags->tag, $for_tags))
					{
						if (!$hasBackendUser) {
							$count = count(Database::getInstance()->prepare("SELECT tl_tag.tid FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND tag = ?" .  " AND (start='' OR start<?) AND (stop='' OR stop>?) AND invisible<>1" . " AND from_table = ? AND tl_tag.tid IN (" . implode(",", $ids) . ")")
							->execute($objTags->tag, 'tl_content', time(), time())
							->fetchAllAssoc());
						\array_push($tags, array('tag_name' => $objTags->tag, 'tag_count' => $count));
						} else {
							$count = count(Database::getInstance()->prepare("SELECT tl_tag.tid FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND tag = ?" .  " AND from_table = ? AND tl_tag.tid IN (" . implode(",", $ids) . ")")
							->execute($objTags->tag, 'tl_content')
							->fetchAllAssoc());
						\array_push($tags, array('tag_name' => $objTags->tag, 'tag_count' => $count));
						}
					}
				}
			}
			if (count($tags))
			{
				$arrCloudTags = $this->cloud_tags($tags);
			}
		}
		return $arrCloudTags;
	}

  public function getTagList($blnExcludeUnpublishedItems = true)
	{
		$hasBackendUser = System::getContainer()->get('contao.security.token_checker')->hasBackendUser();
		$showUnpublished = System::getContainer()->get('contao.security.token_checker')->isPreviewMode();
		$hasFrontendUser = System::getContainer()->get('contao.security.token_checker')->hasFrontendUser();

		if (count($this->arrCloudTags) == 0)
		{
			if (count($this->arrContentElements))
			{
				if (!$hasBackendUser) {
					$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND from_table = ?" . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND invisible<>1"  . " AND tl_tag.tid IN (" . implode(',', $this->arrContentElements) . ") GROUP BY tag ORDER BY tag ASC")
					->execute('tl_content', time(), time());
				\array_push($tags, array('tag_name' => $objTags->tag, 'tag_count' => $count));
				} else {
					$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_content WHERE tl_tag.tid = tl_content.id AND from_table = ?" .  " AND tl_tag.tid IN (" . implode(',', $this->arrContentElements) . ") GROUP BY tag ORDER BY tag ASC")
					->execute('tl_content');
				}
				$list = "";
				$tags = array();
				if ($objTags->numRows)
				{
					while ($objTags->next())
					{
						\array_push($tags, array('tag_name' => $objTags->tag, 'tag_count' => $objTags->count));
					}
				}
				if (count($tags))
				{
					$this->arrCloudTags = $this->cloud_tags($tags);
				}
			}
		}
		return $this->arrCloudTags;
	}

	protected function getRelevantPages($page_id)
	{
		$objPageWithId = Database::getInstance()->prepare("SELECT id, published, start, stop FROM tl_page WHERE pid=? ORDER BY sorting")
			->execute($page_id);
		while ($objPageWithId->next())
		{
			if ($objPageWithId->published && (strlen($objPageWithId->start) == 0 || $objPageWithId->start < time()) && (strlen($objPageWithId->end) == 0 || $objPageWithId->end > time()))
			{
				\array_push($this->arrPages, $objPageWithId->id);
			}
			$this->getRelevantPages($objPageWithId->id);
		}
	}

	protected function getContentElementsForPages()
	{
		$hasBackendUser = System::getContainer()->get('contao.security.token_checker')->hasBackendUser();
		$showUnpublished = System::getContainer()->get('contao.security.token_checker')->isPreviewMode();
		$hasFrontendUser = System::getContainer()->get('contao.security.token_checker')->hasFrontendUser();

		$this->arrContentElements = array();
		if (count($this->arrPages))
		{
			$time = time();
			if (!$hasBackendUser) {
				$arrArticles = Database::getInstance()->prepare("SELECT id FROM tl_article WHERE pid IN (" . implode(',', $this->arrPages) . ") " .  " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1"  . " ORDER BY sorting")
				->execute($time, $time)->fetchEach('id');
			$this->arrContentElements = Database::getInstance()->prepare("SELECT id FROM tl_content WHERE pid IN (" . implode(',', $arrArticles) . ") " ." ORDER BY sorting")
				->execute()->fetchEach('id');
			\array_push($tags, array('tag_name' => $objTags->tag, 'tag_count' => $count));
			} else {
				$arrArticles = Database::getInstance()->prepare("SELECT id FROM tl_article WHERE pid IN (" . implode(',', $this->arrPages) . ") " . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" . " ORDER BY sorting")
				->execute($time, $time)->fetchEach('id');
			$this->arrContentElements = Database::getInstance()->prepare("SELECT id FROM tl_content WHERE pid IN (" . implode(',', $arrArticles) . ") " ." ORDER BY sorting")
				->execute()->fetchEach('id');
			}
		}
	}

	/**
	 * Add specific attributes
	 * @param string
	 * @param mixed
	 */
	public function __set($strKey, $varValue)
	{
		switch ($strKey)
		{
			case 'content_pages':
				// find all articles in this page and all subpages
				$this->getRelevantPages($varValue[0]);
				$this->getContentElementsForPages();
				break;
			case 'inColumn':
				$this->inColumn = $varValue;
				break;
			default:
				parent::__set($strKey, $varValue);
				break;
		}
	}

	/**
	 * Return a parameter
	 * @return string
	 * @throws Exception
	 */
	public function __get($strKey)
	{
		switch ($strKey)
		{
			case 'content_pages':
				return $this->arrContentElements;
				break;
			case 'inColumn':
				return $this->inColumn;
			default:
				return parent::__get($strKey);
				break;
		}
	}
}
