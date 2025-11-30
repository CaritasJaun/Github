(function () {
  if (!window.Swal || typeof Swal.fire !== 'function') return;
  const _fire = Swal.fire.bind(Swal);

  // Accept both modern `{icon:'success'}` and legacy `{type:'success'}`
  Swal.fire = function (arg1, arg2, arg3) {
    // Legacy signature: Swal.fire(title, text, type)
    if (typeof arg1 === 'string') {
      return _fire({ title: arg1, text: arg2, type: arg3 });
    }
    const opts = (arg1 && typeof arg1 === 'object') ? Object.assign({}, arg1) : {};
    if (!opts.type && opts.icon) { opts.type = opts.icon; delete opts.icon; }
    return _fire(opts);
  };
})();
