<?php

namespace Hschottm\TagsBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Doctrine\DBAL\Connection;


class ContentCallbackListener
{
    public function __construct(
        private readonly Connection $db,
    )
    {
    }

    #[AsCallback(table: 'tl_content', target: 'config.oncopy')]
    public function onCopyContent(int $insertId, DataContainer $dc): void
    {
        if (!$dc->id) {
            return;
        }

		$objTags = $this->db->prepare("SELECT * FROM tl_tag WHERE tid = ? AND from_table = ?")->executeQuery(array($dc->id, $dc->table));
		$tags = array();
        while (($row = $objTags->fetchAssociative()) !== false) {
			\array_push($tags, array("table" => $dc->table, "tag" => $row['tag']));
		}
		foreach ($tags as $entry) {
			$this->db->prepare("INSERT INTO tl_tag (tid, tag, from_table) VALUES (?, ?, ?)")->executeQuery(array($insertId, $entry['tag'], $entry['table']));
		}
    }

    #[AsCallback(table: 'tl_content', target: 'config.ondelete')]
    public function onDeleteContent(DataContainer $dc, int $undoId): void
    {
        if (!$dc->id) {
            return;
        }

        $this->db->prepare("DELETE FROM tl_tag WHERE from_table = ? AND tid = ?")
            ->executeQuery(array($dc->table, $dc->id));
    }
}
