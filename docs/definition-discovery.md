# Definition discovery

`Hook\Discovery` reads attribute declarations into `HandlerDefinition` and `CallbackDefinition`. Constructors normalize metadata; discovery adds the declaring class, reflected method and argument count, lazy invocation policy, REST registration settings, and initializer injection tokens. It never binds a container or runtime handler to an attribute.

Metadata subclasses use the same definition pipeline. Constructors and `get_declaration()` can customize metadata; `Infuse::get_tokens()` supplies injection metadata. Legacy execution and wiring overrides produce an actionable migration error, including when their type appears in cached metadata. See the [F6 migration](decorator-compatibility.md).

Priorities, conditions, dynamic providers, and handler instances remain unresolved during metadata discovery. Parser traverses definitions and composition. Preloading creates definitions containing callback tokens; it does not initialize handlers. Definitions serialize to the existing `type` / `args` / `params` arrays. Repeated declarations retain deterministic token suffixes.

Factory converts definitions to runtime services, with one stored runtime per token. Rediscovery preserves that runtime's state. Supplied handler instances are attached to runtime handlers, and supplied callback runtimes retain their identity. Containerless handler helpers also return runtime handlers; callback execution requires a container.

Attributes may still be constructed during reflection. F6 removes decorator services and mutators; it does not claim reflection-free execution or introduce a new cache schema.
