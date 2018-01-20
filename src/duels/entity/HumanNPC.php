<?php

namespace duels\entity;

use core\CorePlayer;
use core\language\LanguageUtils;
use core\Utils;
use duels\arena\Arena;
use duels\duel\Duel;
use duels\Main;
use duels\session\PlayerSession;
use pocketmine\entity\Human;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\StringTag;
use pocketmine\network\protocol\AddPlayerPacket;
use pocketmine\network\protocol\MovePlayerPacket;
use pocketmine\Player;
use pocketmine\utils\TextFormat as TF;

class HumanNPC extends Human {

	public $defaultYaw = 180;
	public $defaultPitch = 0;
	private $type = "";
	private $customName = "Duels";
	protected $hasReset = [];

	public function getType() {
		return $this->type;
	}

	public function setCustomName($name) {
		$this->customName = $name;
	}

	public function spawnTo(Player $player) {
		if($player !== $this and !isset($this->hasSpawned[$player->getId()])) {
			$this->hasSpawned[$player->getId()] = $player;

			$this->server->updatePlayerListData($this->getUniqueId(), $this->getId(), "", $player->getSkinName(), $player->getSkinData(), "", "", "", "", [$player]);

			$pk = new AddPlayerPacket();
			$pk->uuid = $this->getUniqueId();
			$pk->username = $this->getNameTag();
			$pk->eid = $this->getId();
			$pk->x = $this->x;
			$pk->y = $this->y;
			$pk->z = $this->z;
			$pk->speedX = 0;
			$pk->speedY = 0;
			$pk->speedZ = 0;
			$pk->yaw = $this->yaw;
			$pk->pitch = $this->pitch;
			//$pk->item = $this->getInventory()->getItemInHand();
			$pk->metadata = $this->dataProperties;
			$player->dataPacket($pk);

			$this->inventory->sendArmorContents($player);
			$this->level->addPlayerHandItem($this, $player);
		}
	}

	public function showPlaying($count) {
		$this->setNameTag(TF::YELLOW . LanguageUtils::centerPrecise($this->customName . "\n\n\n\n\n\n\n\n\n\n\n\n" . TF::BOLD . TF::YELLOW . $count . " playing" . TF::RESET, null));
	}

	public function center($toCentre, $checkAgainst) {
		if(strlen($toCentre) >= strlen($checkAgainst)) {
			return $toCentre;
		}

		$times = floor((strlen($checkAgainst) - strlen($toCentre)) / 2);
		return str_repeat(" ", ($times > 0 ? $times : 0)) . $toCentre;
	}

	public function saveNBT() {
		return false;
	}

	public function look(Player $player) {
		$x = $this->x - $player->x;
		$y = $this->y - $player->y;
		$z = $this->z - $player->z;
		$yaw = asin($x / sqrt($x * $x + $z * $z)) / 3.14 * 180;
		$pitch = round(asin($y / sqrt($x * $x + $z * $z + $y * $y)) / 3.14 * 180);
		if($z > 0) $yaw = -$yaw + 180;

		$pk = new MovePlayerPacket();
		$pk->eid = $this->id;
		$pk->x = $this->x;
		$pk->y = $this->y + $this->getEyeHeight();
		$pk->z = $this->z;
		$pk->bodyYaw = $yaw;
		$pk->pitch = $pitch;
		$pk->yaw = $yaw;
		$pk->mode = 0;
		$player->dataPacket($pk);
		if($this->isReset($player)) unset($this->hasReset[$player->getId()]);
	}

	public function resetLook(Player $player) {
		if(!$this->isReset($player)) {
			$pk = new MovePlayerPacket();
			$pk->eid = $this->id;
			$pk->x = $this->x;
			$pk->y = $this->y + $this->getEyeHeight();
			$pk->z = $this->z;
			$pk->bodyYaw = $this->defaultYaw;
			$pk->pitch = $this->defaultPitch;
			$pk->yaw = $this->defaultYaw;
			$pk->mode = 0;
			$player->dataPacket($pk);
			$this->hasReset[$player->getId()] = $player;
		}
	}

	protected function initEntity() {
		parent::initEntity();
		if(isset($this->namedtag->customName) and $this->namedtag->customName instanceof StringTag) {
			$this->customName = $this->namedtag["customName"];
		}
		if(isset($this->namedtag->Type) and $this->namedtag->Type instanceof StringTag) {
			$this->type = $this->namedtag["Type"];
		}
	}

	public function isReset(Player $player) {
		return isset($this->hasReset[$player->getId()]);
	}

	public function attack($damage, EntityDamageEvent $source) {
		if($source instanceof EntityDamageByEntityEvent) {
			$attacker = $source->getDamager();
			if($attacker instanceof CorePlayer) {
				$plugin = Main::getInstance();
				$session = $plugin->getSessionManager()->get($attacker->getName());
				if($session instanceof PlayerSession) {
					if(!$session->inDuel()) {
						if($session->inParty()) {
							if($session->getParty()->isOwner($attacker)) {
								if($this->getType() === Duel::TYPE_1V1) {
									if(count($session->getParty()->getPlayers()) === 2) {
										$players = [];
										foreach($session->getParty()->getPlayers() as $name => $uid) {
											$attacker = Utils::getPlayerByUUID($uid);
											if($attacker instanceof Player and $attacker->isOnline()) {
												$players[] = $attacker;
												if(count($players) === 2)
													break;
											} else {
												$attacker->sendMessage(TF::RED . "Cannot join duel due to {$name} being offline!");
												return;
											}
										}
										$arena = $plugin->getArenaManager()->find();
										if((!$arena instanceof Arena) or isset($this->plugin->duelManager->duels[$arena->getId()])) {
											$attacker->sendMessage(TF::RED . "Cannot find an open arena!");
											return;
										}
										$plugin->arenaManager->remove($arena->getId());
										$duel = new Duel($plugin, Duel::TYPE_1V1, $arena, $plugin->getKitManager()->findRandom());
										$session->lastSelectedKit = null;
										foreach($players as $p) {
											$duel->addPlayer($p);
										}
										$plugin->duelManager->duels[$arena->getId()] = $duel;
									} else {
										$attacker->sendPopup(TF::GOLD . "You can only play 1v1's in a party that has two players!");
									}
								} elseif($this->getType() === Duel::TYPE_2V2) {
									if(count($session->getParty()->getPlayers()) === 4) {
										$players = [];
										foreach($session->getParty()->getPlayers() as $name => $uid) {
											$attacker = Utils::getPlayerByUUID($uid);
											if($attacker instanceof Player and $attacker->isOnline()) {
												$players[] = $attacker;
												if(count($players) === 4)
													break;
											} else {
												$attacker->sendMessage(TF::RED . "Cannot join duel due to {$name} being offline!");
												return;
											}
										}
										$arena = $plugin->getArenaManager()->find();
										if(!($arena instanceof Arena) or isset($this->plugin->duelManager->duels[$arena->getId()])) {
											$attacker->sendMessage(TF::RED . "Cannot find an open arena!");
											return;
										}
										$plugin->arenaManager->remove($arena->getId());
										$duel = new Duel($plugin, Duel::TYPE_2V2, $arena, $plugin->getKitManager()->findRandom());
										$session->lastSelectedKit = null;
										foreach($players as $p) {
											$duel->addPlayer($p);
										}
										$plugin->duelManager->duels[$arena->getId()] = $duel;
									} else {
										$attacker->sendPopup(TF::GOLD . "You can only play 2v2's in a party that has four players!!");
									}
								} else {
									$attacker->sendPopup(TF::GOLD . "You've managed to break something!");
								}
							} else {
								$attacker->sendMessage(TF::RED . "Only the party leader can join a duel!");
							}
						} else {
							$plugin->duelManager->findDuel($attacker, $this->getType(), null, true);
						}
					} else {
						$attacker->sendPopup(TF::RED . "You're already in a duel!");
					}
				}
			}
		}

		parent::attack($damage, $source);
	}

}