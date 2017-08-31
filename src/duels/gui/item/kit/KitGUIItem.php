<?php
/**
 * Created by PhpStorm.
 * User: Jack
 * Date: 20/4/17
 * Time: 8:58 PM
 */

namespace duels\gui\item\kit;

use core\gui\item\GUIItem;
use core\language\LanguageUtils;
use duels\gui\containers\KitSelectionContainer;
use duels\kit\Kit;
use duels\Main;
use pocketmine\item\Item;

class KitGUIItem extends GUIItem {

	const GUI_ITEM_ID = "kit_gui_selector";

	/** @var Kit */
	private $kit = null;

	public function __construct(Item $item, KitSelectionContainer $parent = null) {
		parent::__construct($item, $parent);
		$plugin = Main::getInstance();
		$kit = $plugin->getKitManager()->get($item->getNamedTag()["KitName"]);
		if(!$kit instanceof Kit) {
			throw new \InvalidStateException("No kit specified for display item!");
		}
		if($kit->getType() === "kit_select" or $kit->getType() === "random_kit") {
			$this->kit = $kit;
		}
		$this->setCustomName($kit->getDisplayName() . " Kit");
		$this->setPreviewName($this->getName());
		$this->setPreviewDescription(LanguageUtils::translateColors("&7Select a kit to continue"));
	}

	public function getKit() {
		return $this->kit;
	}

}