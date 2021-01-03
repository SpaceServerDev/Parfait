<?php

/*
 *               _ _
 *         /\   | | |
 *        /  \  | | |_ __ _ _   _
 *       / /\ \ | | __/ _` | | | |
 *      / ____ \| | || (_| | |_| |
 *     /_/    \_|_|\__\__,_|\__, |
 *                           __/ |
 *                          |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author TuranicTeam
 * @link https://github.com/TuranicTeam/Altay
 *
 */

declare(strict_types=1);

namespace pocketmine\entity\projectile;

use pocketmine\block\Water;
use pocketmine\entity\Entity;
use pocketmine\entity\object\ItemEntity;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\player\PlayerFishEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\FishingRod;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\level\Level;
use pocketmine\level\particle\GenericParticle;
use pocketmine\level\particle\Particle;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\RayTraceResult;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\ActorEventPacket;
use pocketmine\network\mcpe\protocol\PlaySoundPacket;
use pocketmine\network\mcpe\protocol\TextPacket;
use pocketmine\Player;
use pocketmine\Server;
use function cos;
use function floor;
use function mt_rand;
use function sin;
use function sqrt;

class FishingHook extends Projectile{

	public const NETWORK_ID = self::FISHING_HOOK;

	/** @var float */
	public $height = 0.15;
	public $width = 0.15;
	protected $gravity = 0.1;
	protected $drag = 0.05;

	/** @var Entity|null */
	protected $caughtEntity;
	/** @var int */
	protected $ticksCatchable = 0;
	protected $ticksCaughtDelay = 0;
	protected $ticksCatchableDelay = 0;
	/** @var float */
	protected $fishApproachAngle = 0;

	public function attack(EntityDamageEvent $source) : void{
		if($source instanceof EntityDamageByEntityEvent){
			$source->setCancelled();
		}
		parent::attack($source);
	}

	/**
	 * FishingHook constructor.
	 *
	 * @param Level       $level
	 * @param CompoundTag $nbt
	 * @param null|Entity $owner
	 */
	public function __construct(Level $level, CompoundTag $nbt, ?Entity $owner = null){
		parent::__construct($level, $nbt, $owner);

		if($owner instanceof Player){
			$owner->setFishingHook($this);

			$this->handleHookCasting($this->motion->x, $this->motion->y, $this->motion->z, 1.5, 1.0);
		}
	}

	/**
	 * @param Entity         $entityHit
	 * @param RayTraceResult $hitResult
	 */
/*	public function onHitEntity(Entity $entityHit, RayTraceResult $hitResult) : void{
		$entityHit->attack(new EntityDamageByEntityEvent($this, $entityHit, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0));

		$this->mountEntity($entityHit);
	}*/

	/**
	 * @return bool
	 */
	public function canBePushed() : bool{
		return false;
	}

	/**
	 * @param float $x
	 * @param float $y
	 * @param float $z
	 * @param float $f1
	 * @param float $f2
	 */
	public function handleHookCasting(float $x, float $y, float $z, float $f1, float $f2){
		$f = sqrt($x * $x + $y * $y + $z * $z);
		$x = $x / $f;
		$y = $y / $f;
		$z = $z / $f;
		$x = $x + $this->random->nextSignedFloat() * 0.0075 * $f2;
		$y = $y + $this->random->nextSignedFloat() * 0.0075 * $f2;
		$z = $z + $this->random->nextSignedFloat() * 0.0075 * $f2;
		$x = $x * $f1;
		$y = $y * $f1;
		$z = $z * $f1;
		$this->motion->x += $x;
		$this->motion->y += $y;
		$this->motion->z += $z;
	}

	public function onUpdate(int $currentTick) : bool{
		if($this->closed) return false;

		$owner = $this->getOwningEntity();

		$inGround = $this->level->getBlock($this)->isSolid();

		if($inGround){
			$this->motion->x *= $this->random->nextFloat() * 0.2;
			$this->motion->y *= $this->random->nextFloat() * 0.2;
			$this->motion->z *= $this->random->nextFloat() * 0.2;
		}

		$hasUpdate = parent::onUpdate($currentTick);

		if($owner instanceof Player){
			if(!($owner->getInventory()->getItemInHand() instanceof FishingRod) or !$owner->isAlive() or $owner->isClosed() or $owner->distanceSquared($this) > 1024){
				$this->flagForDespawn();
			}

			if(!$inGround){
				$hasUpdate = true;

				$f6 = 0.92;

				if($this->onGround or $this->isCollidedHorizontally){
					$f6 = 0.5;
				}

				$d10 = 0;

				$bb = $this->getBoundingBox();

				for($j = 0; $j < 5; ++$j){
					$d1 = $bb->minY + ($bb->maxY - $bb->minY) * $j / 5;
					$d3 = $bb->minY + ($bb->maxY - $bb->minY) * ($j + 1) / 5;

					$bb2 = new AxisAlignedBB($bb->minX, $d1, $bb->minZ, $bb->maxX, $d3, $bb->maxZ);

					if($this->level->isLiquidInBoundingBox($bb2, new Water())){
						$d10 += 0.2;
					}
				}

				if($this->isValid() and $d10 > 0){
					$l = 1;

					// TODO: lightninstrike

					if($this->ticksCatchable > 0){
						--$this->ticksCatchable;

						if($this->ticksCatchable <= 0){
							$this->ticksCaughtDelay = 0;
							$this->ticksCatchableDelay = 0;
						}
					}elseif($this->ticksCatchableDelay > 0){
						$this->ticksCatchableDelay -= $l;

						if($this->ticksCatchableDelay <= 0){
							$this->broadcastEntityEvent(ActorEventPacket::FISH_HOOK_HOOK);
							$this->motion->y -= 0.2;
							$this->ticksCatchable = mt_rand(10, 30);
						}else{
							$this->fishApproachAngle = $this->fishApproachAngle + $this->random->nextSignedFloat() * 4.0;
							$f7 = $this->fishApproachAngle * 0.01745;
							$f10 = sin($f7);
							$f11 = cos($f7);
							$d13 = $this->x + ($f10 * $this->ticksCatchableDelay * 0.1);
							$d15 = $this->y + 1;
							$d16 = $this->z + ($f11 * $this->ticksCatchableDelay * 0.1);
							$block1 = $this->level->getBlock(new Vector3($d13, $d15 - 1, $d16));

							if($block1 instanceof Water){
								if($this->random->nextFloat() < 0.15){
									$this->level->addParticle(new GenericParticle(new Vector3($d13, $d15 - 0.1, $d16), Particle::TYPE_BUBBLE));
								}

								$this->level->addParticle(new GenericParticle(new Vector3($d13, $d15, $d16), Particle::TYPE_WATER_WAKE));
							}
						}
					}elseif($this->ticksCaughtDelay > 0){
						$this->ticksCaughtDelay -= $l;
						$f1 = 0.15;

						if($this->ticksCaughtDelay < 20){
							$f1 = ($f1 + (20 - $this->ticksCaughtDelay) * 0.05);
						}elseif($this->ticksCaughtDelay < 40){
							$f1 = ($f1 + (40 - $this->ticksCaughtDelay) * 0.02);
						}elseif($this->ticksCaughtDelay < 60){
							$f1 = ($f1 + (60 - $this->ticksCaughtDelay) * 0.01);
						}

						if($this->random->nextFloat() < $f1){
							$f9 = mt_rand(0, 360) * 0.01745;
							$f2 = mt_rand(25, 60);
							$d12 = $this->x + (sin($f9) * $f2 * 0.1);
							$d14 = floor($this->y) + 1.0;
							$d6 = $this->z + (cos($f9) * $f2 * 0.1);
							$block = $this->level->getBlock(new Vector3($d12, $d14 - 1, $d6));

							if($block instanceof Water){
								$this->level->addParticle(new GenericParticle(new Vector3($d12, $d14, $d6), Particle::TYPE_SPLASH));
							}
						}

						if($this->ticksCaughtDelay <= 0){
							$this->ticksCatchableDelay = mt_rand(20, 80);
							$this->fishApproachAngle = mt_rand(0, 360);
						}
					}else{
						$this->ticksCaughtDelay = mt_rand(100, 900);
						$this->ticksCaughtDelay -= 20 * 5; // TODO: Lure
					}

					if($this->ticksCatchable > 0){
						$this->motion->y -= ($this->random->nextFloat() * $this->random->nextFloat() * $this->random->nextFloat()) * 0.2;
					}
				}

				$d11 = $d10 * 2.0 - 1.0;
				$this->motion->y += 0.04 * $d11;

				if($d10 > 0.0){
					$f6 = $f6 * 0.9;
					$this->motion->y *= 0.8;
				}

				$this->motion->x *= $f6;
				$this->motion->y *= $f6;
				$this->motion->z *= $f6;
			}
		}else{
			$this->flagForDespawn();
		}

		return $hasUpdate;
	}

	public function close() : void{
		parent::close();

		$owner = $this->getOwningEntity();
		if($owner instanceof Player){
			$owner->setFishingHook(null);
		}
		$this->dismountEntity();
	}

	public function handleHookRetraction() : void{
		$angler = $this->getOwningEntity();
		if($this->isValid() and $angler instanceof Player){
			if($this->getRidingEntity() != null){
				$ev = new PlayerFishEvent($angler, $this, PlayerFishEvent::STATE_CAUGHT_ENTITY);
				$ev->call();

				if(!$ev->isCancelled()){
					$d0 = $angler->x - $this->x;
					$d2 = $angler->y - $this->y;
					$d4 = $angler->z - $this->z;
					$d6 = sqrt($d0 * $d0 + $d2 * $d2 + $d4 * $d4);
					$d8 = 0.1;
					$this->getRidingEntity()->setMotion(new Vector3($d0 * $d8, $d2 * $d8 + sqrt($d6) * 0.08, $d4 * $d8));
				}
			}elseif($this->ticksCatchable > 0){
				$rnd=mt_rand(0,500);

				/**@var $items Item*/
				$items = [
					Item::get(349,0,1), Item::get(460,0,1), Item::get(461,0,1), Item::get(462,0,1)
				];
				list($iur,$isr,$ir,$ur,$sr,$r)=$this->getRareFishingHook($angler);
				if($rnd<=$iur) {
					$result = Item::get(278, 0, 1);
					$result->setCustomName("§d恒星の輝きを放つピッケル");
					$result->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(15), 4));
					$result->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(17), 3));
					$this->sendJukeboxPopup($angler, "[釣りAI]§a輝いてる§c§l謎のピッケル§r§aが釣れた！");
				} else if($rnd>$iur && $rnd <= $isr) {
					$item = Item::get(378, 0, $amount);
					$item->setCustomName("§d修復クリーム");
					$this->sendJukeboxPopup($angler,"[釣りAI]§aねばねばの§c§l修復クリーム§r§aが釣れた！");
				} else if($rnd > $isr and $rnd <= $ir){
					$result = Item::get(mt_rand(500,511), 0, 1);
					$this->sendJukeboxPopup($angler, "[釣りAI]§aん？これは...§c§lレコード§r§aが釣れた！");
				}else{
					/**  @var $result Item */
					$rand=mt_rand(1,100);
					switch ($angler->getLevel()->getFolderName()) {
						//宇宙:深海魚
						case "space":
							if ($rand <= $ur) {
								switch (mt_rand(1,4)) {
									case 1:
										$result = $items[2];
										$size = mt_rand(180, 250);
										$result->setCustomName("§eシーラカンス");
										$result->setLore(["§f".$size."cm"]);
										$name = "シーラカンス";
										break;
									case 2:
										$result = $items[2];
										$size = mt_rand(1800, 2000);
										$result->setCustomName("§eメガロドン §f" . $size . "cm");
										$name = "メガロドン";
										break;
									case 3:
										$result = $items[2];
										$size = mt_rand(300, 1100);
										$result->setCustomName("§eリュウグウノツカイ §f" . $size . "cm");
										$name = "リュウグウノツカイ";
										break;
									case 4:
										if($this->canUseFishFeed($angler)) {
											switch ($this->getPlayerFishFeed($angler)) {
												case 1:

													$result = $items[2];
													$size = mt_rand(300000, 600000);
													$result->setCustomName("§eオキナ §f" . $size . "cm");
													$name = "オキナ";
													$pk2 = new PlaySoundPacket;
													$pk2->soundName = "random.explode";
													$pk2->x = $angler->x;
													$pk2->y = $angler->y;
													$pk2->z = $angler->z;
													$pk2->volume = 0.5;
													$pk2->pitch = 1;
													Server::getInstance()->broadcastPacket($angler->getLevel()->getPlayers(), $pk2);
													break;
												case 2:
													$result = $items[2];
													$size = mt_rand(20000, 28000);
													$result->setCustomName("§eクラーケン §f" . $size . "cm");
													$name = "クラーケン";
													break;
												case 3:
													$result = $items[2];
													$size = mt_rand(8000, 18000);
													$result->setCustomName("§eダイオウイカ §f" . $size . "cm");
													$name = "ダイオウイカ";
													break;
												case 4:
													$result = $items[2];
													$size = mt_rand(1200000, 1500000);
													$result->setCustomName("§e赤えい §f" . $size . "cm");
													$name = "赤えい";
													break;
												case 5:
													$result = $items[2];
													$size = mt_rand(10000, 12000);
													$result->setCustomName("§eカメロケラス §f" . $size . "cm");
													$name = "カメロケラス";
													break;
												case 6:
													$result = $items[2];
													$size = mt_rand(30000, 50000);
													$result->setCustomName("§eアスピドケロン §f" . $size . "cm");
													$name = "アスピドケロン";
													break;
												default:
													$result = $items[2];
													$size = mt_rand(300, 1100);
													$result->setCustomName("§eリュウグウノツカイ §f" . $size . "cm");
													$name = "リュウグウノツカイ";
													break;
											}
										}else {
											$result = $items[2];
											$size = mt_rand(300, 1100);
											$result->setCustomName("§eリュウグウノツカイ §f" . $size . "cm");
											$name = "リュウグウノツカイ";
											break;
										}
									break;
								}
								foreach ($angler->getServer()->getOnlinePlayers()as$p) {
									if ($p !== $angler) {
										$this->sendJukeboxPopup($p,"§a".$angler->getName()."§eが宇宙で深海魚§l§c" . $name . " (".$size."cm)§r§eを釣り上げた！！！！！");
									}
								}
								$result->getNamedTag()->setInt("Fish_rare",4);
								$result->getNamedTag()->setInt("Fish_size",$size);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eこれは！！とんでもない深海魚の§5§l" . $name . " (".$size."cm)§r§eが釣れた！");
							} elseif ($rand > $ur and $rand <= $sr) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[1];
										$result->setCustomName("§cマンボウ");
										$name = "マンボウ";
									break;
									case 2:
										$result = $items[1];
										$result->setCustomName("§cミツクリザメ");
										$name = "ミツクリザメ";
									break;
									case 3:
										$result = $items[1];
										$result->setCustomName("§cラブカ");
										$name = "ラブカ";
									break;
									case 4:
										$result = $items[1];
										$result->setCustomName("§cリーフィーシードラゴン");
										$name = "リフィーシードラゴン";
									break;
									case 5:
										$result = $items[1];
										$result->setCustomName("§cガラスイカ");
										$name = "ガラスイカ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",3);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eわお！！超レア深海魚の§d§l" . $name . "§r§eが釣れた！");
							} elseif ($rand > $sr and $rand <= $r) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§bスターゲイザーフィッシュ");
										$name = "スターゲイザーフィッシュ";
									break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§bブロブフィッシュ");
										$name = "ブロブフィッシュ";
									break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§bホウライエソ");
										$name = "ホウライエソ";
									break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§bミズウオ");
										$name = "ミズウオ";
									break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§bオニハダカ");
										$name = "オニハダカ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",2);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eおっ！レア深海魚の§c§l" . $name . "§r§eが釣れた！");
							} else {
								switch (mt_rand(1, 9)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§aミドリフサアンコウ");
										$name = "ミドリフサアンコウ";
										break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§aツボダイ");
										$name = "ツボダイ";
										break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§aデメニギス");
										$name = "デメニギス";
										break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§aハプロフリュネー・モリス");
										$name = "ハプロフリューネ・モリス";
										break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§aフサアンコウ");
										$name = "フサアンコウ";
										break;
									case 6:
										$result = $items[0];
										$result->setCustomName("§aコンニャクウオ");
										$name = "コンニャクウオ";
										break;
									case 7:
										$result = $items[0];
										$result->setCustomName("§aダンゴウオ");
										$name = "ダンゴウオ";
										break;
									case 8:
										$result = $items[0];
										$result->setCustomName("§aヨミノアシロ");
										$name = "ヨミノアシロ";
										break;
									case 9:
										$result = $items[0];
										$result->setCustomName("§aシンカイクサウオ");
										$name = "シンカイクサウオ";
										break;
								}
								$result->getNamedTag()->setInt("Fish_rare",1);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§a深海魚の§b§l" . $name . "§r§aが釣れた！");
							}
							break;
						//地球:海魚
						case "earth":
							if ($rand <= $ur) {
								switch (mt_rand(1,5)) {
									case 1:
										$result = $items[2];
										$size = mt_rand(100, 250);
										$result->setCustomName("§eマグロ §f" . $size . "cm");
										$name = "マグロ";
										break;
									case 2:
										$result = $items[2];
										$size = mt_rand(300, 600);
										$result->setCustomName("§eホオジロザメ §f" . $size . "cm");
										$name = "ホオジロザメ";
										break;
									case 3:
										$result = $items[2];
										$size = mt_rand(280, 360);
										$result->setCustomName("§eカジキ §f" . $size . "cm");
										$name = "カジキ";
										break;
									case 4:
										$result = $items[2];
										$size = mt_rand(750, 980);
										$result->setCustomName("§eシャチ §f" . $size . "cm");
										$name = "シャチ";
										break;
									case 5:
										if ($this->canUseFishFeed($angler)) {
											switch ($this->getPlayerFishFeed($angler)) {
												case 1:
													$result = $items[2];
													$size = mt_rand(15, 40);
													$result->setCustomName("§eドリアスピス §f" . $size . "cm");
													$name = "ドリアスピス";
													break;
												case 2:
													$result = $items[2];
													$size = mt_rand(300, 600);
													$result->setCustomName("§eエデスタス §f" . $size . "cm");
													$name = "エデスタス";
													break;
												case 3:
													$result = $items[2];
													$size = mt_rand(250, 350);
													$result->setCustomName("§eヘリコプリオン §f" . $size . "cm");
													$name = "ヘリコプリオン";
													break;
												case 4:
													$result = $items[2];
													$size = mt_rand(1200, 2500);
													$result->setCustomName("§eリオプレウロドン §f" . $size . "cm");
													$name = "リオプレウロドン";
													break;
												case 5:
													$result = $items[2];
													$size = mt_rand(10000, 11000);
													$result->setCustomName("§eニューネッシー §f" . $size . "cm");
													$name = "ニューネッシー";
													break;
												case 6:
													$result = $items[2];
													$size = mt_rand(900, 15000);
													$result->setCustomName("§eキャディ §f" . $size . "cm");
													$name = "キャディ";
													break;
												default:
													$result = $items[2];
													$size = mt_rand(280, 360);
													$result->setCustomName("§eカジキ §f" . $size . "cm");
													$name = "カジキ";
													break;
											}
										} else {
											$result = $items[2];
											$size = mt_rand(280, 360);
											$result->setCustomName("§eカジキ §f" . $size . "cm");
											$name = "カジキ";
										}
										break;
								}
								foreach ($angler->getServer()->getOnlinePlayers()as$p) {
									if ($p !== $angler) {
										$this->sendJukeboxPopup($p,"§a".$angler->getName()."§eが地球で激レア海魚の§l§c" . $name . " (".$size."cm)§r§eを釣り上げた！！！！！");
									}
								}
								$result->getNamedTag()->setInt("Fish_rare",4);
								$result->getNamedTag()->setInt("Fish_size",$size);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eこれは！！とんでもない海魚の§5§l" . $name . " (".$size."cm)§r§eが釣れた！");
							} elseif ($rand > $ur and $rand <= $sr) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[1];
										$result->setCustomName("§cマダイ");
										$name = "マダイ";
									break;
									case 2:
										$result = $items[1];
										$result->setCustomName("§cノコギリザメ");
										$name = "ノコギリザメ";
									break;
									case 3:
										$result = $items[1];
										$result->setCustomName("§cジンベエザメ");
										$name = "ジンベエザメ";
									break;
									case 4:
										$result = $items[1];
										$result->setCustomName("§cシュモクザメ");
										$name = "シュモクザメ";
									break;
									case 5:
										$result = $items[1];
										$result->setCustomName("§cコバンザメ");
										$name = "コバンザメ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",3);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eわお！！海魚の§d§l" . $name . "§r§eが釣れた！");
							} elseif ($rand > $sr and $rand <= $r) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§bサバ");
										$name = "サバ";
									break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§bサケ");
										$name = "サケ";
									break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§bブリ");
										$name = "ブリ";
									break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§bウツボ");
										$name = "ウツボ";
									break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§bカツオ");
										$name = "カツオ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",2);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler, "[釣りAI]§eおっ！海魚の§c§l" . $name . "§r§eが釣れた！");
							} else {
								switch (mt_rand(1, 9)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§aスズキ");
										$name = "スズキ";
										break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§aオニカサゴ");
										$name = "オニカサゴ";
										break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§aアカエイ");
										$name = "アカエイ";
										break;
									case 4:
										$result = $items[3];
										$result->setCustomName("§aクロサバフグ");
										$name = "クロサバフグ";
										break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§aサワラ");
										$name = "サワラ";
										break;
									case 6:
										$result = $items[0];
										$result->setCustomName("§aタチウオ");
										$name = "タチウオ";
										break;
									case 7:
										$result = $items[0];
										$result->setCustomName("§aボラ");
										$name = "ボラ";
										break;
									case 8:
										$result = $items[0];
										$result->setCustomName("§aマルアジ");
										$name = "マルアジ";
										break;
									case 9:
										$result = $items[0];
										$result->setCustomName("§aヤリイカ");
										$name = "ヤリイカ";
										break;
								}
								$result->getNamedTag()->setInt("Fish_rare",1);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§a海魚の§b§l" . $name . "§r§aが釣れた！");
							}
							break;
						//海王星:なんか氷割って釣るやつとか湖とか適当に
						case "Neptune":
							if ($rand <= $ur) {
								switch (mt_rand(1, 4)) {
									case 1:
										$result = $items[2];
										$size = mt_rand(100, 250);
										$result->setCustomName("§eマグロ §f" . $size . "cm");
										$name = "マグロ";
										break;
									case 2:
										$result = $items[2];
										$size = mt_rand(60, 120);
										$result->setCustomName("§eウナギ §f" . $size . "cm");
										$name = "ウナギ";
										break;
									case 3:
										$result = $items[0];
										$size = mt_rand(40, 80);
										$result->setCustomName("§eホッケ §f" . $size . "cm");
										$name = "ホッケ";
										break;
									case 4:
										if ($this->canUseFishFeed($angler)) {
											switch ($this->getPlayerFishFeed($angler)) {
												case 1:
													$result = $items[2];
													$size = mt_rand(1000, 1800);
													$result->setCustomName("§eネッシー §f" . $size . "cm");
													$name = "ネッシー";
													break;
												case 2:
													$result = $items[2];
													$size = mt_rand(1000, 2500);
													$result->setCustomName("§eイッシー §f" . $size . "cm");
													$name = "イッシー";
													break;

												case 3:
													$result = $items[2];
													$size = mt_rand(1000, 1800);
													$result->setCustomName("§eクッシー §f" . $size . "cm");
													$name = "クッシー";
													break;
												case 4:
													$result = $items[2];
													$size = mt_rand(3000, 3500);
													$result->setCustomName("§eモッシー §f" . $size . "cm");
													$name = "モッシー";
													break;
												case 5:
													$result = $items[2];
													$size = mt_rand(1000, 1800);
													$result->setCustomName("§eチュッシー §f" . $size . "cm");
													$name = "チュッシー";
													break;

												case 6:
													$result = $items[2];
													$size = mt_rand(1000, 1800);
													$result->setCustomName("§eアッシー §f" . $size . "cm");
													$name = "アッシー";
													break;

												default:
													$result = $items[2];
													$size = mt_rand(60, 120);
													$result->setCustomName("§eウナギ §f" . $size . "cm");
													$name = "ウナギ";
													break;
											}
										}else {
											$result = $items[2];
											$size = mt_rand(60, 120);
											$result->setCustomName("§eウナギ §f" . $size . "cm");
											$name = "ウナギ";
											break;
										}
								}
								foreach ($angler->getServer()->getOnlinePlayers()as$p) {
									if ($p !== $angler) {
										$this->sendJukeboxPopup($p,"§a".$angler->getName()."§eが海王星で激レア魚の§l§c" . $name . " (".$size."cm)§r§eを釣り上げた！！！！！");
									}
								}
								$result->getNamedTag()->setInt("Fish_rare",4);
								$result->getNamedTag()->setInt("Fish_size",$size);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eこれは！！とんでもない魚の§5§l" . $name . " (".$size."cm)§r§eが釣れた！");
							} elseif ($rand > $ur and $rand <= $sr) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[1];
										$result->setCustomName("§cキス");
										$name = "キス";
									break;
									case 2:
										$result = $items[1];
										$result->setCustomName("§cニシン");
										$name = "ニシン";
									break;
									case 3:
										$result = $items[1];
										$result->setCustomName("§cエビ");
										$name = "エビ";
									break;
									case 4:
										$result = $items[1];
										$result->setCustomName("§cヒラメ");
										$name = "ヒラメ";
									break;
									case 5:
										$result = $items[1];
										$result->setCustomName("§cナマズ");
										$name = "ナマズ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",3);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eわお！！魚の§d§l" . $name . "§r§eが釣れた！");
							} elseif ($rand > $sr and $rand <= $r) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§bブラックバス");
										$name = "サバ";
									break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§bヒラマサ");
										$name = "ヒラマサ";
									break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§bエイ");
										$name = "エイ";
									break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§bコチ");
										$name = "コチ";
									break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§bライギョ");
										$name = "ライギョ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",2);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler, "[釣りAI]§eおっ！ちょいレア魚の§c§l" . $name . "§r§eが釣れた！");
							} else {
								switch (mt_rand(1, 9)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§aサヨリ");
										$name = "サヨリ";
										break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§aコイ");
										$name = "コイ";
										break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§aデメキン");
										$name = "デメキン";
										break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§aワカサギ");
										$name = "ワカサギ";
										break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§aタイ");
										$name = "タイ";
										break;
									case 6:
										$result = $items[3];
										$result->setCustomName("§aフグ");
										$name = "フグ";
										break;
									case 7:
										$result = $items[0];
										$result->setCustomName("§aブルーギル");
										$name = "ブルーギル";
										break;
									case 8:
										$result = $items[0];
										$result->setCustomName("§aシーバス");
										$name = "シーバス";
										break;
									case 9:
										$result = $items[0];
										$result->setCustomName("§aハゼ");
										$name = "ハゼ";
										break;
								}
								$result->getNamedTag()->setInt("Fish_rare",1);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§a魚の§b§l" . $name . "§r§aが釣れた！");
							}
							break;
						//火星:川？
						case "mars":
							if ($rand <= $ur) {
								switch (mt_rand(1,4)) {
									case 1:
										$result = $items[2];
										$size = mt_rand(400, 500);
										$result->setCustomName("§eナイルパーチ §f" . $size . "cm");
										$name = "ナイルパーチ";
										break;
									case 2:
										$result = $items[2];
										$size = mt_rand(60, 120);
										$result->setCustomName("§eウナギ §f" . $size . "cm");
										$name = "ウナギ";
										break;
									case 3:
										$result = $items[0];
										$size = mt_rand(1000, 2300);
										$result->setCustomName("§eヘラチョウザメ §f" . $size . "cm");
										$name = "ヘラチョウザメ";
										break;
									case 4:
										if ($this->canUseFishFeed($angler)) {
											switch ($this->getPlayerFishFeed($angler)) {
												case 1:

													$result = $items[2];
													$size = mt_rand(2600, 3600);
													$result->setCustomName("§eオオメジロサメ §f" . $size . "cm");
													$name = "オオメジロサメ";

													break;
												case 2:
													$result = $items[2];
													$size = mt_rand(200, 300);
													$result->setCustomName("§eメコンオオナマズ §f" . $size . "cm");
													$name = "メコンオオナマズ";

													break;
												case 3:
													$result = $items[2];
													$size = mt_rand(230, 360);
													$result->setCustomName("§eデンキウナギ §f" . $size . "cm");
													$name = "デンキウナギ";

													break;
												case 4:
													$result = $items[2];
													$size = mt_rand(180, 250);
													$result->setCustomName("§eアハイア・グランディ §f" . $size . "cm");
													$name = "アハイア・グランディ";

													break;
												case 5:
													$result = $items[2];
													$size = mt_rand(200, 280);
													$result->setCustomName("§eアマゾンカワイルカ §f" . $size . "cm");
													$name = "アマゾンカワイルカ";

													break;
												case 6:
													$result = $items[2];
													$size = mt_rand(170, 280);
													$result->setCustomName("§eアリゲーターガー §f" . $size . "cm");
													$name = "アリゲーターガー";
													break;
												default:
													$result = $items[2];
													$size = mt_rand(60, 120);
													$result->setCustomName("§eウナギ §f" . $size . "cm");
													$name = "ウナギ";
													break;
											}
										} else {
											$result = $items[2];
											$size = mt_rand(60, 120);
											$result->setCustomName("§eウナギ §f" . $size . "cm");
											$name = "ウナギ";
											break;
										}
										break;
								}
								foreach ($angler->getServer()->getOnlinePlayers()as$p) {
									if ($p !== $angler) {
										$this->sendJukeboxPopup($p,"§a".$angler->getName()."§eが火星で激レア魚の§l§c" . $name . " (".$size."cm)§r§eを釣り上げた！！！！！");
									}
								}
								$result->getNamedTag()->setInt("Fish_rare",4);
								$result->getNamedTag()->setInt("Fish_size",$size);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eこれは！！とんでもない魚の§5§l" . $name . " (".$size."cm)§r§eが釣れた！");
							} elseif ($rand > $ur and $rand <= $sr) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[1];
										$result->setCustomName("§cベタ");
										$name = "ベタ";
									break;
									case 2:
										$result = $items[1];
										$result->setCustomName("§cエンドリケリー");
										$name = "エンドリケリー";
									break;
									case 3:
										$result = $items[1];
										$result->setCustomName("§cピラルク");
										$name = "ピラルク";
									break;
									case 4:
										$result = $items[1];
										$result->setCustomName("§cドラド");
										$name = "ドラド";
									break;
									case 5:
										$result = $items[1];
										$result->setCustomName("§cアロワナ");
										$name = "アロワナ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",3);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eわお！！魚の§d§l" . $name . "§r§eが釣れた！");
							} elseif ($rand > $sr and $rand <= $r) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§bティラピア");
										$name = "ティラピア";
									break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§b河ふぐ");
										$name = "河ふぐ";
									break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§bニジマス");
										$name = "ニジマス";
									break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§bケツギョ");
										$name = "ケツギョ";
									break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§bカワアナゴ");
										$name = "カワアナゴ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare",2);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler, "[釣りAI]§eおっ！ちょいレア魚の§c§l" . $name . "§r§eが釣れた！");
							} else {
								switch (mt_rand(1, 9)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§aハヤ");
										$name = "ハヤ";
										break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§aコイ");
										$name = "コイ";
										break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§aアユ");
										$name = "アユ";
										break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§aサクラマス");
										$name = "サクラマス";
										break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§aワカサギ");
										$name = "ワカサギ";
										break;
									case 6:
										$result = $items[3];
										$result->setCustomName("§aイトウ");
										$name = "イトウ";
										break;
									case 7:
										$result = $items[0];
										$result->setCustomName("§aスズキ");
										$name = "スズキ";
										break;
									case 8:
										$result = $items[0];
										$result->setCustomName("§aドジョウ");
										$name = "ドジョウ";
										break;
									case 9:
										$result = $items[0];
										$result->setCustomName("§aナマズ");
										$name = "ナマズ";
										break;
								}
								$result->getNamedTag()->setInt("Fish_rare",1);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§a魚の§b§l" . $name . "§r§aが釣れた！");
							}
							break;
						//フラット:池？
/*						case "flatworld":
							if ($rand <= $ur) {
								switch (mt_rand(1,4)) {
									case 1:
										$result = $items[2];
										$size = mt_rand(400, 500);
										$result->setCustomName("§eナイルパーチ §f" . $size . "cm");
										$name = "ナイルパーチ";
										break;
									case 2:
										$result = $items[2];
										$size = mt_rand(60, 120);
										$result->setCustomName("§eウナギ §f" . $size . "cm");
										$name = "ウナギ";
										break;
									case 3:
										$result = $items[0];
										$size = mt_rand(1000, 2300);
										$result->setCustomName("§eヘラチョウザメ §f" . $size . "cm");
										$name = "ヘラチョウザメ";
										break;
									case 4:
										switch ($this->getPlayerFishFeed($angler)){
											case 1:
												if($this->canUseFishFeed($angler)) {
													$result = $items[2];
													$size = mt_rand(2600, 3600);
													$result->setCustomName("§eオオメジロザメ §f" . $size . "cm");
													$name = "オオメジロザメ";
												}
												break;
											case 2:
												if($this->canUseFishFeed($angler)) {
													$result = $items[2];
													$size = mt_rand(200, 300);
													$result->setCustomName("§eメコンオオナマズ §f" . $size . "cm");
													$name = "メコンオオナマズ";
												}
												break;
											case 3:
												if($this->canUseFishFeed($angler)) {
													$result = $items[2];
													$size = mt_rand(230, 360);
													$result->setCustomName("§e電気ウナギ §f" . $size . "cm");
													$name = "電気ウナギ";
												}
												break;
											case 4:
												if($this->canUseFishFeed($angler)) {
													$result = $items[2];
													$size = mt_rand(180, 250);
													$result->setCustomName("§eアハイア・グランディ §f" . $size . "cm");
													$name = "アハイア・グランディ";
												}
												break;
											case 5:
												if($this->canUseFishFeed($angler)) {
													$result = $items[2];
													$size = mt_rand(200, 280);
													$result->setCustomName("§eアマゾンカワイルカ §f" . $size . "cm");
													$name = "アマゾンカワイルカ";
												}
												break;
											case 6:
												if($this->canUseFishFeed($angler)) {
													$result = $items[2];
													$size = mt_rand(170, 280);
													$result->setCustomName("§eアリゲーターガー §f" . $size . "cm");
													$name = "アリゲーターガー";
												}
												break;
											default:
											$result = $items[2];
											$size = mt_rand(60, 120);
											$result->setCustomName("§eウナギ §f" . $size . "cm");
											$name = "ウナギ";
											break;
										}
									break;
								}
								foreach ($angler->getServer()->getOnlinePlayers()as$p) {
									if ($p != $angler) {
										$this->sendJukeboxPopup($p,"§a".$angler->getName()."§eが火星で激レア魚の§l§c" . $name . " (".$size."cm)§r§eを釣り上げた！！！！！");
									}
								}
								$result->getNamedTag()->setInt("Fish_rare","4");
								$result->getNamedTag()->setInt("Fish_size",$size);
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eこれは！！とんでもない魚の§5§l" . $name . " (".$size."cm)§r§eが釣れた！");
							} elseif ($rand > $ur and $rand <= $sr) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[1];
										$result->setCustomName("§cベタ");
										$name = "ベタ";
									break;
									case 2:
										$result = $items[1];
										$result->setCustomName("§cエンドリケリー");
										$name = "エンドリケリー";
									break;
									case 3:
										$result = $items[1];
										$result->setCustomName("§cピラルク");
										$name = "ピラルク";
									break;
									case 4:
										$result = $items[1];
										$result->setCustomName("§cドラド");
										$name = "ドラド";
									break;
									case 5:
										$result = $items[1];
										$result->setCustomName("§cアロワナ");
										$name = "アロワナ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare","3");
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§eわお！！魚の§d§l" . $name . "§r§eが釣れた！");
							} elseif ($rand > $sr and $rand <= $r) {
								switch (mt_rand(1, 5)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§bティラピア");
										$name = "ティラピア";
									break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§b河ふぐ");
										$name = "河ふぐ";
									break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§bニジマス");
										$name = "ニジマス";
									break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§bケツギョ");
										$name = "ケツギョ";
									break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§bカワアナゴ");
										$name = "カワアナゴ";
									break;
								}
								$result->getNamedTag()->setInt("Fish_rare","2");
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler, "[釣りAI]§eおっ！ちょいレア魚の§c§l" . $name . "§r§eが釣れた！");
							} else {
								switch (mt_rand(1, 9)) {
									case 1:
										$result = $items[0];
										$result->setCustomName("§aハヤ");
										$name = "ハヤ";
										break;
									case 2:
										$result = $items[0];
										$result->setCustomName("§aコイ");
										$name = "コイ";
										break;
									case 3:
										$result = $items[0];
										$result->setCustomName("§aアユ");
										$name = "アユ";
										break;
									case 4:
										$result = $items[0];
										$result->setCustomName("§aサクラマス");
										$name = "サクラマス";
										break;
									case 5:
										$result = $items[0];
										$result->setCustomName("§aワカサギ");
										$name = "ワカサギ";
										break;
									case 6:
										$result = $items[3];
										$result->setCustomName("§aイトウ");
										$name = "イトウ";
										break;
									case 7:
										$result = $items[0];
										$result->setCustomName("§aスズキ");
										$name = "スズキ";
										break;
									case 8:
										$result = $items[0];
										$result->setCustomName("§aドジョウ");
										$name = "ドジョウ";
										break;
									case 9:
										$result = $items[0];
										$result->setCustomName("§aナマズ");
										$name = "ナマズ";
										break;
								}
								$result->getNamedTag()->setInt("Fish_rare","1");
								$result->getNamedTag()->setString("Fish_name",$name);
								$this->sendJukeboxPopup($angler,"[釣りAI]§a魚の§b§l" . $name . "§r§aが釣れた！");
							}
						break;*/
						default:
							$result=$items[0];
						break;
					}
				}

				$nbt=$result->getNamedTag();
				$nbt->setInt("Fish",1);
				$result->setNamedTag($nbt);

				$ev = new PlayerFishEvent($angler, $this, PlayerFishEvent::STATE_CAUGHT_FISH, $this->random->nextBoundedInt(6) + 1);
				$ev->call();

				if(!$ev->isCancelled()){
					if($this->canUseFishFeed($angler)){

					}
					$nbt = Entity::createBaseNBT($this);
					$nbt->setTag($result->nbtSerialize(-1, "Item"));

					$entityitem = new ItemEntity($this->level, $nbt);
					$d0 = $angler->x - $this->x;
					$d2 = $angler->y - $this->y;
					$d4 = $angler->z - $this->z;
					$d6 = sqrt($d0 * $d0 + $d2 * $d2 + $d4 * $d4);
					$d8 = 0.1;
					$entityitem->setMotion(new Vector3($d0 * $d8, $d2 * $d8 + sqrt($d6) * 0.08, $d4 * $d8));
					$entityitem->spawnToAll();
					$this->level->dropExperience($angler, $ev->getXpDropAmount());
				}
			}

			$this->flagForDespawn();
		}
	}

	protected function tryChangeMovement() : void{
		// POOP
	}

	private function sendJukeboxPopup(Player $player,string $str){
		$pk = new TextPacket();
		$pk->type = 4;
		$pk->message = $str;
		$player->dataPacket($pk);
	}

	private function canUseFishFeed(Player $player):bool{
		switch ($this->getPlayerFishFeed($player)){
			case 0:
				return true;
			break;
			case 1:
				$bool=false;
				foreach ($player->getInventory()->getContents()as$itm){
					if($itm->getId()===264){
						$bool=true;
					}
				}
				if($bool) {
					$inv = $player->getInventory()->removeItem(Item::get(264, 0, 1));
					$player->sendPopup("§a餌(ダイヤ)を１個消費しました");
					return true;
				}
				$player->sendPopup("§a餌(ダイヤ)が切れてます。");
				return false;
			break;
			case 2:
				$bool=false;
				foreach ($player->getInventory()->getContents()as$itm){
					if($itm->getId()===266){
						$bool=true;
					}
				}
				if($bool) {
					$inv = $player->getInventory()->removeItem(Item::get(266, 0, 1));
					$player->sendPopup("§a餌(金)を１個消費しました");
					return true;
				}
				$player->sendPopup("§a餌(金)が切れてます。");
				return false;
			break;
			case 3:
				$bool=false;
				foreach ($player->getInventory()->getContents()as$itm){
					if($itm->getId()===265){
						$bool=true;
					}
				}
				if($bool) {
					$inv = $player->getInventory()->removeItem(Item::get(265, 0, 1));
					$player->sendPopup("§a餌(鉄)を１個消費しました");
					return true;
				}
				$player->sendPopup("§a餌(鉄)が切れてます。");
				return false;
			break;
			case 4:
				$bool=false;
				foreach ($player->getInventory()->getContents()as$itm){
					if($itm->getId()===414){
						$bool=true;
					}
				}
				if($bool) {
					$inv = $player->getInventory()->removeItem(Item::get(414, 0, 1));
					$player->sendPopup("§a餌(ミミズ)を１個消費しました");
					return true;
				}
				$player->sendPopup("§a餌(ミミズ)が切れてます。");
				return false;
			break;
			case 5:
				$bool=false;
				foreach ($player->getInventory()->getContents()as$itm){
					if($itm->getId()===297){
						$bool=true;
					}
				}
				if($bool) {
					$inv = $player->getInventory()->removeItem(Item::get(297, 0, 1));
					$player->sendPopup("§a餌(パン)を１個消費しました");
					return true;
				}
				$player->sendPopup("§a餌(パン)が切れてます。");
				return false;
			break;
			case 6:
				$bool=false;
				foreach ($player->getInventory()->getContents()as$itm){
					if($itm->getId()===367){
						$bool=true;
					}
				}
				if($bool) {
					$inv = $player->getInventory()->removeItem(Item::get(367, 0, 1));
					$player->sendPopup("§a餌(怪しい薬)を１個消費しました");
					return true;
				}
				$player->sendPopup("§a餌(怪しい薬)が切れてます。");
				return false;
			break;
		}
	}

	private function getPlayerFishFeed(Player $player) : int{
		$nbt=$player->namedtag;
		if(!$nbt->offsetExists("FishFeed")) {
			return 0;
		}
		return $nbt->getInt("FishFeed");
	}

	private function getRareFishingHook(Player $player):array{
		$item=$player->getInventory()->getItemInHand();
		$nbt=$item->getNamedTag();
		if(!$nbt->offsetExists("RareFishingHook")){
			return [1,2,5,1,10,60];
		}
		if($nbt->getInt("RareFishingHook")==1){
			return [1,2,5,3,12,62];
		}
		if($nbt->getInt("RareFishingHook")==2){
			return [1,2,5,5,18,65];
		}
		if($nbt->getInt("RareFishingHook")==3){
			return [2,4,7,8,20,70];
		}
		if($nbt->getInt("RareFishingHook")==4){
			return [3,10,20,10,30,80];
		}
	}

}