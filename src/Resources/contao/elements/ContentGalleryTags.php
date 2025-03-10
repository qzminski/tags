<?php

namespace Hschottm\TagsBundle;

use \Contao\ContentGallery;
use \Contao\Input;
use \Contao\Database;

/**
 * Contao Open Source CMS - tags extension
 *
 * Copyright (c) 2008-2024 Helmut Schottmüller
 *
 * @license LGPL-3.0+
 */

class ContentGalleryTags extends ContentGallery
{
	/**
	 * Generate the content element
	 */
	public function compile()
	{
		$newMultiSRC = array();

		if ((strlen(TagHelper::decode(Input::get('tag'))) && (!$this->tag_ignore)) || (strlen($this->tag_filter))) {
			$tagids = array();

			$relatedlist = (strlen(TagHelper::decode(Input::get('related')))) ? preg_split("/,/", TagHelper::decode(Input::get('related'))) : array();
			$alltags = array_merge(array(TagHelper::decode(Input::get('tag'))), $relatedlist);
			$first = true;
			if (strlen($this->tag_filter)) {
				$headlinetags = preg_split("/,/", $this->tag_filter);
				$tagids = $this->getFilterTags();
				$first = false;
			} else {
				$headlinetags = array();
			}
			foreach ($alltags as $tag) {
				if (strlen(trim($tag))) {
					if (count($tagids)) {
						$tagids = Database::getInstance()->prepare("SELECT tid FROM tl_tag WHERE from_table = ? AND tag = ? AND tid IN (" . implode(",", $tagids) . ")")
							->execute('tl_files', $tag)
							->fetchEach('tid');
					} else if ($first) {
						$tagids = Database::getInstance()->prepare("SELECT tid FROM tl_tag WHERE from_table = ? AND tag = ?")
							->execute('tl_files', $tag)
							->fetchEach('tid');
						$first = false;
					}
				}
			}
			while ($this->objFiles->next()) {
				if ($this->objFiles->type == 'file') {
					if (in_array($this->objFiles->id, $tagids)) \array_push($newMultiSRC, $this->objFiles->uuid);
				} else {
					$objSubfiles = FilesModel::findByPid($this->objFiles->uuid);
					if ($objSubfiles === null) {
						continue;
					}

					while ($objSubfiles->next()) {
						if (in_array($objSubfiles->id, $tagids)) \array_push($newMultiSRC, $objSubfiles->uuid);
					}
				}
			}
			$this->multiSRC = $newMultiSRC;
			$this->objFiles = FilesModel::findMultipleByUuids($this->multiSRC);
			if ($this->objFiles === null) {
				return '';
			}
		}
		parent::compile();
	}

	protected function getFilterTags()
	{
		if (strlen($this->tag_filter)) {
			$tags = preg_split("/,/", $this->tag_filter);
			$placeholders = array();
			foreach ($tags as $tag) {
				\array_push($placeholders, "'" . $tag . "'");
			}
			return Database::getInstance()->prepare("SELECT tid FROM tl_tag WHERE tag IN (" . implode(',', $placeholders) . ") AND from_table = ? ORDER BY tag ASC")
				->execute('tl_files')
				->fetchEach('tid');
		} else {
			return array();
		}
	}
}
