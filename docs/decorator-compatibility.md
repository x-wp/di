# Decorator compatibility and the F6 boundary

F1–F5 separate built-in callback, handler, and module execution from attribute objects. F6 preparation moves shared legacy implementation into internal `XWP\DI\Compatibility` classes and traits. This is code isolation, not immutable attributes: inherited `with_*()`, `load()`, `can_load()`, and `invoke()` APIs remain available.

`Decorators\Hook` retains the existing attribute hierarchy through a compatibility base. Callback traits retain protected override points and parent-method dispatch for custom attribute subclasses. Handler decorators and the new handler runtime share lifecycle methods; `Hook\Handler` does not inherit from an attribute class. Constants remain on classes for PHP 8.1 support.

Three consumers still require the inherited API:

- Factory and Parser bind reflection, container, handler, and parameter metadata during discovery and custom-attribute reconstruction.
- Custom decorator subclasses can override protected registration, argument resolution, and invocation methods. Exact-class runtime routing preserves those overrides.
- Typed `!self.hook` views extend the original callback attribute and forward live operations to their runtime owner. Their declared interfaces and inherited methods remain part of that bridge.

Removing the APIs outright would require a custom-extension migration policy, a separate discovery binder, and a replacement for the current view/interface wiring. Moving the same methods into traits does not satisfy that removal criterion. The strict immutable-decorator work remains open in `di-bao.10`; the compatible isolation is tracked separately in `di-2b2`.

Existing consumers need no change for this isolation. New integrations should use the public helpers and `Can_Handle`/`Can_Hook` contracts. `Compatibility` classes and traits are internal implementation details, not new extension APIs.
