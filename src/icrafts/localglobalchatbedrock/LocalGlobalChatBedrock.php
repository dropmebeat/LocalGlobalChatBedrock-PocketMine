<?php

declare(strict_types=1);

namespace icrafts\localglobalchatbedrock;

use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\player\Player;
use pocketmine\player\chat\LegacyRawChatFormatter;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use function array_values;
use function file_exists;
use function filemtime;
use function is_array;
use function is_string;
use function ltrim;
use function preg_replace;
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

final class LocalGlobalChatBedrock extends PluginBase implements Listener
{
    private const LOCAL_RADIUS = 100.0;
    private const LOCAL_WORD = "\u{0420}\u{044F}\u{0434}\u{043E}\u{043C}";
    private const GLOBAL_WORD = "\u{041C}\u{0438}\u{0440}";
    private const DEFAULT_PREFIX_WORD = "\u{0418}\u{0433}\u{0440}\u{043E}\u{043A}";
    private const EMPTY_GLOBAL_MESSAGE = "\u{0412}\u{0432}\u{0435}\u{0434}\u{0438}\u{0442}\u{0435} \u{0441}\u{043E}\u{043E}\u{0431}\u{0449}\u{0435}\u{043D}\u{0438}\u{0435} \u{043F}\u{043E}\u{0441}\u{043B}\u{0435} !";

    private ?Config $pexConfig = null;
    private int $pexConfigMtime = 0;

    public function onEnable(): void
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("LocalGlobalChatBedrock enabled.");
    }

    /**
     * @priority MONITOR
     */
    public function onPlayerChat(PlayerChatEvent $event): void
    {
        $player = $event->getPlayer();
        $rawMessage = $event->getMessage();
        $prefixTag = $this->buildPexPrefixTag($player);

        if (str_starts_with($rawMessage, "!")) {
            $message = ltrim(substr($rawMessage, 1));
            if ($message === "") {
                $event->cancel();
                $player->sendMessage(
                    TextFormat::colorize("&c" . self::EMPTY_GLOBAL_MESSAGE),
                );
                return;
            }

            $event->setMessage($message);
            $event->setFormatter(
                new LegacyRawChatFormatter(
                    TextFormat::colorize(
                        $this->buildChannelTag(true) . $prefixTag . " {%1}",
                    ),
                ),
            );
            return;
        }

        $recipients = [];
        foreach ($event->getRecipients() as $recipient) {
            if ($recipient instanceof Player) {
                if (
                    $recipient->getWorld()->getFolderName() ===
                        $player->getWorld()->getFolderName() &&
                    $recipient
                        ->getPosition()
                        ->distance($player->getPosition()) <= self::LOCAL_RADIUS
                ) {
                    $recipients[] = $recipient;
                }
            } else {
                $recipients[] = $recipient;
            }
        }

        if (!$this->containsPlayer($recipients, $player)) {
            $recipients[] = $player;
        }

        $event->setRecipients(array_values($recipients));
        $event->setFormatter(
            new LegacyRawChatFormatter(
                TextFormat::colorize(
                    $this->buildChannelTag(false) . $prefixTag . " {%1}",
                ),
            ),
        );
    }

    private function buildChannelTag(bool $global): string
    {
        $color = $global ? "&6" : "&7";
        $word = $global ? self::GLOBAL_WORD : self::LOCAL_WORD;
        return "&f[" . $color . $word . "&f]";
    }

    private function buildPexPrefixTag(Player $player): string
    {
        $prefix = $this->resolvePexPrefixFor($player);
        $prefix = str_replace(["[", "]"], "", $prefix);
        $prefix = trim($prefix);
        $prefix = preg_replace("/\\s+/u", "", $prefix) ?? $prefix;
        if ($prefix === "") {
            $prefix = "&7" . self::DEFAULT_PREFIX_WORD;
        }
        return "&f[" . rtrim($prefix) . "&f]";
    }

    private function resolvePexPrefixFor(Player $player): string
    {
        $data = $this->getPexData();
        if ($data === null) {
            return "&7" . self::DEFAULT_PREFIX_WORD;
        }

        $groups = $this->getPexGroups($data);
        $users = $this->getPexUsers($data);
        $defaultGroup = strtolower(
            (string) ($data["default-group"] ?? "default"),
        );
        $name = strtolower($player->getName());
        $userData =
            isset($users[$name]) && is_array($users[$name])
                ? $users[$name]
                : [];

        $userPrefix = trim((string) ($userData["prefix"] ?? ""));
        if ($userPrefix !== "") {
            return $userPrefix;
        }

        $userGroups = [];
        if (isset($userData["groups"]) && is_array($userData["groups"])) {
            foreach ($userData["groups"] as $group) {
                if (is_string($group)) {
                    $userGroups[] = strtolower(trim($group));
                }
            }
        }
        if ($userGroups === []) {
            $userGroups = [$defaultGroup];
        }

        foreach ($userGroups as $groupName) {
            if (
                isset($groups[$groupName]) &&
                is_array($groups[$groupName]) &&
                trim((string) ($groups[$groupName]["prefix"] ?? "")) !== ""
            ) {
                return (string) $groups[$groupName]["prefix"];
            }
        }

        return "&7" . self::DEFAULT_PREFIX_WORD;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getPexData(): ?array
    {
        $path =
            $this->getServer()->getDataPath() .
            "plugin_data/PermissionsExBedrock/permissions.yml";
        if (!file_exists($path)) {
            return null;
        }

        $mtime = (int) (filemtime($path) ?: 0);
        if ($this->pexConfig === null || $mtime !== $this->pexConfigMtime) {
            $this->pexConfig = new Config($path, Config::YAML);
            $this->pexConfigMtime = $mtime;
        }

        $all = $this->pexConfig->getAll();
        return is_array($all) ? $all : null;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function getPexGroups(array $data): array
    {
        $groupsRaw = $data["groups"] ?? [];
        if (!is_array($groupsRaw)) {
            return [];
        }

        $groups = [];
        foreach ($groupsRaw as $groupName => $groupData) {
            if (is_string($groupName)) {
                $groups[strtolower($groupName)] = is_array($groupData)
                    ? $groupData
                    : [];
            }
        }
        return $groups;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function getPexUsers(array $data): array
    {
        $usersRaw = $data["users"] ?? [];
        if (!is_array($usersRaw)) {
            return [];
        }

        $users = [];
        foreach ($usersRaw as $userName => $userData) {
            if (is_string($userName)) {
                $users[strtolower($userName)] = is_array($userData)
                    ? $userData
                    : [];
            }
        }
        return $users;
    }

    /**
     * @param CommandSender[] $recipients
     */
    private function containsPlayer(array $recipients, Player $target): bool
    {
        foreach ($recipients as $recipient) {
            if ($recipient instanceof Player && $recipient === $target) {
                return true;
            }
        }
        return false;
    }
}
