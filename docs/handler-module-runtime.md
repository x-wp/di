# Handler and module runtime extraction

Exact built-in `Handler`, `Module`, `Ajax_Handler`, `REST_Handler`, and `CLI_Handler` attributes now produce separate runtime objects in `XWP\DI\Hook`. Container tokens remain `Hook-{class}`. `HandlerDefinition::from_data()` retains unresolved conditions, priorities, tag modifiers, strategies, callback IDs, and Infuse metadata. `ModuleDefinition::from_data()` retains imports, handlers, and services.

Discovery and cache serialization still use decorator metadata. `Hook\Factory` creates runtime objects when the container resolves that metadata. Custom attribute subclasses retain their existing behavior. The parser/compiler cache layout is unchanged; specialized handler priorities and module services are now retained when generating new metadata.

`!self.handler`, the public handler helpers, and `Invoker` use the existing `Can_Handle`/`Can_Import` contracts. The token entry and injected handler remain the same object. For built-in attributes that object is now a runtime, not an instance of the concrete decorator class. Consumer type declarations should use `Can_Handle`, `Can_Import`, or the specialized handler interfaces rather than concrete attribute classes.

The established lifecycle stays intact: application construction does not start the root module; `App::run()` registers it; `Invoker` schedules initialization, callback attachment, and module composition. Context checks, initialization retries, Infuse resolution, asynchronous configuration, and supplied-instance identity retain their previous behavior. Runtime objects keep initialization and callback state; definitions hold metadata.

Use `get_definition()` on a runtime handler to inspect its initial metadata. Runtime callback discovery and supplied-instance binding do not mutate that definition.

The handler runtime shares lifecycle implementation with legacy decorators through internal `Compatibility` adapters. It does not inherit from an attribute class. Inherited decorator mutators remain available for existing discovery and custom-subclass paths; [F6 API removal remains open](decorator-compatibility.md).
