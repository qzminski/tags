<?php

namespace Hschottm\TagsBundle;

use Contao\Database;
use Contao\System;

/**
 * Class TagListArticles
 *
 * Provide methods to handle tag input fields.
 * @copyright  Helmut Schottmüller 2008-2024
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    Controller
 */

class TagListArticles extends TagList
{
	protected $arrArticles = array();
	protected $arrPages = array();
	protected $inColumn = "";

  public function getRelatedTagList($for_tags, $blnExcludeUnpublishedItems = true)
	{
		$hasBackendUser = System::getContainer()->get('contao.security.token_checker')->hasBackendUser();
		$showUnpublished = System::getContainer()->get('contao.security.token_checker')->isPreviewMode();
		$hasFrontendUser = System::getContainer()->get('contao.security.token_checker')->hasFrontendUser();

		if (!is_array($for_tags)) return array();
		if (!count($this->arrArticles)) return array();

		$request = System::getContainer()->get('request_stack')->getCurrentRequest();
		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$blnExcludeUnpublishedItems = false;
		}

		$ids = array();
		for ($i = 0; $i < count($for_tags); $i++)
		{
			if (!$hasBackendUser) {
				$arr = Database::getInstance()->prepare("SELECT DISTINCT tl_tag.tid FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND from_table = ?  AND tl_tag.tid IN (" . implode(',', $this->arrArticles) . ") AND tag = ?" . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" . " ORDER BY tl_tag.tid ASC")
				->execute('tl_article', $for_tags[$i], time(), time())
				->fetchEach('tid');
			} else {
				$arr = Database::getInstance()->prepare("SELECT DISTINCT tl_tag.tid FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND from_table = ?  AND tl_tag.tid IN (" . implode(',', $this->arrArticles) . ") AND tag = ?" . " ORDER BY tl_tag.tid ASC")
				->execute('tl_article', $for_tags[$i])
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
				$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND from_table = ?" . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" . " AND tl_tag.tid IN (" . implode(",", $ids) . ") GROUP BY tag ORDER BY tag ASC")
				->execute('tl_article', time(), time());
			} else {
				$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND from_table = ?" . " AND tl_tag.tid IN (" . implode(",", $ids) . ") GROUP BY tag ORDER BY tag ASC")
				->execute('tl_article');
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
							$count = count(Database::getInstance()->prepare("SELECT tl_tag.tid FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND tag = ?" . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" . " AND from_table = ? AND tl_tag.tid IN (" . implode(",", $ids) . ")")
							->execute($objTags->tag, time(), time(), 'tl_article')
							->fetchAllAssoc());
							\array_push($tags, array('tag_name' => $objTags->tag, 'tag_count' => $count));
						} else {
							$count = count(Database::getInstance()->prepare("SELECT tl_tag.tid FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND tag = ?" . " AND from_table = ? AND tl_tag.tid IN (" . implode(",", $ids) . ")")
							->execute($objTags->tag, 'tl_article')
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
			if (count($this->arrArticles))
			{
				if (!$hasBackendUser) {
					$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND from_table = ?" . " AND (start='' OR start<?) AND (stop='' OR stop>?) AND published=1" . " AND tl_tag.tid IN (" . implode(',', $this->arrArticles) . ") GROUP BY tag ORDER BY tag ASC")
					->execute('tl_article', time(), time());
				} else {
					$objTags = Database::getInstance()->prepare("SELECT tag, COUNT(tag) as count FROM tl_tag, tl_article WHERE tl_tag.tid = tl_article.id AND from_table = ?" . " AND tl_tag.tid IN (" . implode(',', $this->arrArticles) . ") GROUP BY tag ORDER BY tag ASC")
					->execute('tl_article');
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

	/**
	 * Add specific attributes
	 * @param string
	 * @param mixed
	 */
	public function __set($strKey, $varValue)
	{
		switch ($strKey)
		{
			case 'articles':
				// find all articles in this page and all subpages
				\array_push($this->arrPages, $varValue[0]);
				$this->getRelevantPages($varValue[0]);
				$this->getArticlesForPages();
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
			case 'articles':
				return $this->arrArticles;
				break;
			case 'inColumn':
				return $this->inColumn;
			default:
				return parent::__get($strKey);
				break;
		}
	}
}
