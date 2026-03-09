# LocalGlobalChat

**LocalGlobalChat** is a lightweight chat management plugin for **PocketMine-MP** that divides communication into two distinct channels: **Local** for nearby players and **Global** for server-wide announcements.

## Features

*   **Dual Chat Channels:** Seamlessly switch between shouting to everyone or whispering to neighbors.
*   **Distance-Based Local Chat:** Define exactly how many blocks away a message can be heard.
*   **Global Chat Prefix:** Use a special character (like `!`) to send global messages instantly.
*   **Custom Formatting:** Fully customizable chat tags and colors for both channels.
*   **Spy Mode:** Allow administrators to see all local messages regardless of distance.
*   **Anti-Spam:** Integrated cooldowns to prevent chat flooding.

## How to Use

*   **Local Chat:** Just type in chat. Only players within the defined radius will see it.
*   **Global Chat:** Start your message with `!` (e.g., `!Hello everyone!`) to broadcast to the whole server.

## Commands


| Command | Description | Permission |
|---------|-------------|------------|
| `/chat spy` | Enable/Disable Admin Spy mode | `lgchat.admin.spy` |
| `/chat reload` | Reload the plugin configuration | `lgchat.admin.reload` |

## Configuration

```yaml
# LocalGlobalChat Settings
settings:
  # Max distance for local messages
  local_radius: 50
  # Symbol required for global chat
  global_symbol: "!"
  
format:
  local: "§8[§7LOCAL§8] §f{player}: {message}"
  global: "§8[§bGLOBAL§8] §f{player}: {message}"
