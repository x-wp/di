# Specialized callback runtime

The F1–F4 ports from `definition-split-plan.md` now route callback attribute families through separate runtime objects:

| Decorator | Runtime |
| --- | --- |
| `Dynamic_Filter`, `Dynamic_Action` | `Hook\Dynamic_Callback` |
| `Ajax_Action` | `Hook\Ajax_Callback` |
| `REST_Route` | `Hook\REST_Callback` |
| `CLI_Command` | `Hook\CLI_Callback` |

These classes extend `Hook\Callback`. Discovery now emits built-in callback definitions from unbound declaration metadata, and existing cache entries are read through `Hook\Factory`. The serialized metadata format is unchanged. Custom metadata subclasses use the same runtime families. Legacy execution overrides require the [F6 migration](decorator-compatibility.md).

`!self.hook` supplies the callback runtime itself, identical to the token entry. Type-hint `Hook\Callback` or its specialized subclass. Use `$hook->target` for WordPress hook removal. REST route registration always uses the runtime callable; a standard REST response callback still calls the handler directly.

AJAX metadata now retains injected parameters. REST metadata retains its invocation strategy and handler-specific registration tag and priority. Older REST metadata receives missing registration settings from its handler. Values already omitted by an older cache, such as AJAX injection parameters or a nondefault REST invocation strategy, cannot be recovered without rebuilding that cache.

REST responses preserve their existing dispatch semantics: WordPress handles route permissions, response errors propagate, and hook invocation flags do not gate responses. The runtime tracks response attempts and clears its firing state on success and failure.

Verification covers dynamic mappings and providers, AJAX request extraction and nonce/capability guards, REST schemas/guards/responses, CLI formatting and real WP-CLI registration, runtime injection, and cold/warm hook caches and compiled containers. F5 subsequently adds [handler/module runtimes](handler-module-runtime.md). F6 removes inherited decorator runtime methods; see [migration details](decorator-compatibility.md).
