# Handler and module runtime extraction

Exact built-in `Handler`, `Module`, `Ajax_Handler`, `REST_Handler`, and `CLI_Handler` attributes now produce separate runtime objects in `XWP\DI\Hook`. Container tokens remain `Hook-{class}`. `HandlerDefinition::from_data()` retains unresolved conditions, priorities, tag modifiers, strategies, callback IDs, and Infuse metadata. `ModuleDefinition::from_data()` retains imports, handlers, and services.

Discovery reads built-in definitions from unbound constructor metadata; cache serialization keeps the existing metadata arrays. `Hook\Factory` creates runtime objects when the container resolves that metadata. Custom attribute subclasses retain their existing behavior. The parser/compiler cache layout is unchanged; specialized handler priorities and module services are now retained when generating new metadata.

`!self.handler`, the public handler helpers, and `Invoker` use the existing `Can_Handle`/`Can_Import` contracts. The token entry and injected handler remain the same object. For built-in attributes that object is now a runtime, not an instance of the concrete decorator class. Consumer type declarations should use `Can_Handle`, `Can_Import`, or the specialized handler interfaces rather than concrete attribute classes.

The established lifecycle stays intact: application construction does not start the root module; `App::run()` registers it; `Invoker` schedules initialization, callback attachment, and module composition. Context checks, initialization retries, Infuse resolution, asynchronous configuration, and supplied-instance identity retain their previous behavior. Runtime objects keep initialization and callback state; definitions hold metadata.

Use `get_definition()` on a runtime handler to inspect its initial metadata. Runtime callback discovery and supplied-instance binding do not mutate that definition.

The handler runtime shares lifecycle implementation with legacy decorators through internal `Compatibility` adapters. It does not inherit from an attribute class. Inherited decorator mutators remain available for custom discovery and imperative compatibility paths; [F6 API removal remains open](decorator-compatibility.md).

Supplied objects retain their external initialization lifecycle: adoption does not call `can_initialize()`, `configure_async()`, or `on_initialize()`. This includes REST controllers. Before adopting a controller, its owner must configure its namespace and basename and call `on_initialize()` if it relies on the base controller's route-registration listener. Adoption preserves that target and does not repeat its initialization. A declared `INIT_USER` handler remains pending until an instance is supplied. Self-adoption during container construction is different: the container's pending initialization still finishes once.

Repeated registration attaches newly supplied callback tokens after the handler's original attachment point without reinstalling its lifecycle. Lazy initialization notifications use `get_lazy_tag()` and are scoped by application UUID; integrations listening for those internal notifications should use that accessor.

The concrete handler type change is a beta breaking change. Code accepting
`Decorators\Handler` or `Decorators\Module` for `!self.handler` must use
`Can_Handle` or `Can_Import`; CLI consumers can use `Can_Handle_CLI`. CLI runtime
handlers provide the same `choice()`, `prompt()`, `track()`, `tick()`, `finish()`
helpers and readable `description` metadata as the legacy handler.
