(function (Drupal, once) {
  const timers = new WeakMap();
  const counters = context => [...(context.matches?.('.inside-counter') ? [context] : []), ...context.querySelectorAll('.inside-counter')];
  Drupal.behaviors.insideEmbeddedContentCounter = {
    attach(context) {
      once('inside-counter', counters(context)).forEach(element => {
        const timer = setInterval(() => { element.textContent = Number(element.textContent) + 1; }, 1000);
        timers.set(element, timer);
      });
    },
    detach(context, settings, trigger) {
      if (trigger === 'unload') {
        once.remove('inside-counter', counters(context)).forEach(element => {
          clearInterval(timers.get(element));
          timers.delete(element);
        });
      }
    },
  };
})(Drupal, once);
