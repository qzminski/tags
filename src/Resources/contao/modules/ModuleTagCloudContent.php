<?php

namespace Hschottm\TagsBundle;

/**
 * @copyright  Helmut Schottmüller 2008-2024
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    tags
 * @license    LGPL
 * @filesource
 */

 use \Contao\System;
 use \Contao\Module;
 use \Contao\BackendTemplate;
 use Contao\Input;
 use Contao\StringUtil;


/**
 * Class ModuleTagCloudContent
 *
 * @copyright  Helmut Schottmüller 2008-2024
 * @author     Helmut Schottmüller <https://github.com/hschottm>
 * @package    Controller
 */
class ModuleTagCloudContent extends ModuleTagCloud
{
	/**
	 * Display a wildcard in the back end
	 * @return string
	 */
	public function generate()
	{
		$request = System::getContainer()->get('request_stack')->getCurrentRequest();
		if ($request && System::getContainer()->get('contao.routing.scope_matcher')->isBackendRequest($request))
		{
			$objTemplate = new BackendTemplate('be_wildcard');
			$objTemplate->wildcard = '### TAGCLOUD Content ###';

			return $objTemplate->parse();
		}

		$this->strTemplate = (strlen($this->cloud_template)) ? $this->cloud_template : $this->strTemplate;

		$taglist = new TagListContentElements();
		$taglist->addNamedClass = $this->tag_named_class;
		if ($this->tag_on_page_class) $this->checkForContentElementOnPage = true;
		if (strlen($this->tag_maxtags)) $taglist->maxtags = $this->tag_maxtags;
		if (strlen($this->tag_topten_number) && $this->tag_topten_number > 0) $taglist->topnumber = $this->tag_topten_number;
		if (strlen($this->tag_buckets) && $this->tag_buckets > 0) $taglist->buckets = $this->tag_buckets;
		if (strlen($this->tag_content_pages)) $taglist->content_pages = StringUtil::deserialize($this->tag_content_pages, TRUE);
		$this->arrTags = $taglist->getTagList();
		if ($this->tag_topten) $this->arrTopTenTags = $taglist->getTopTenTagList();
		if (strlen(TagHelper::decode(Input::get('tag'))) && $this->tag_related)
		{
			$relatedlist = (strlen(TagHelper::decode(Input::get('related')))) ? preg_split("/,/", TagHelper::decode(Input::get('related'))) : array();
			$this->arrRelated = $taglist->getRelatedTagList(array_merge(array(TagHelper::decode(Input::get('tag'))), $relatedlist));
		}
		if (count($this->arrTags) < 1)
		{
			return '';
		}
		return Module::generate();
	}
}
