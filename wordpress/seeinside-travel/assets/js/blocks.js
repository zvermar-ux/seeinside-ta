/* Gutenberg blocks: SeeInside Travel section / list / slider / search / flights / charters / status */
(function (wp) {
  if (!wp || !wp.blocks) return;
  var el = wp.element.createElement, SSR = wp.serverSideRender, IC = wp.blockEditor.InspectorControls, C = wp.components;
  var sections = (window.STA_BLOCKS && window.STA_BLOCKS.sections) || {};
  var cat = 'widgets', icon = 'airplane';

  wp.blocks.registerBlockType('seeinside-travel/section', {
    title: 'SeeInside Travel – section', icon: icon, category: cat,
    attributes: { section: { type: 'string', default: 'layout' }, postId: { type: 'number', default: 0 } },
    edit: function (p) {
      return el('div', null,
        el(IC, null, el(C.PanelBody, { title: 'SeeInside Travel' },
          el(C.SelectControl, { label: 'Section', value: p.attributes.section, options: Object.keys(sections).map(function (k) { return { value: k, label: sections[k] }; }), onChange: function (v) { p.setAttributes({ section: v }); } }),
          el(C.TextControl, { label: 'Post ID (empty = current)', type: 'number', value: p.attributes.postId || '', onChange: function (v) { p.setAttributes({ postId: +v || 0 }); } }))),
        el(SSR, { block: 'seeinside-travel/section', attributes: p.attributes }));
    },
    save: function () { return null; }
  });

  wp.blocks.registerBlockType('seeinside-travel/list', {
    title: 'SeeInside Travel – offers', icon: 'grid-view', category: cat,
    attributes: { type: { type: 'string', default: 'tour' }, limit: { type: 'number', default: 6 }, columns: { type: 'number', default: 3 }, lastMinute: { type: 'boolean', default: false }, destination: { type: 'string', default: '' }, orderby: { type: 'string', default: 'date' } },
    edit: function (p) {
      var a = p.attributes, set = function (k) { return function (v) { var o = {}; o[k] = v; p.setAttributes(o); }; };
      return el('div', null,
        el(IC, null, el(C.PanelBody, { title: 'SeeInside Travel' },
          el(C.SelectControl, { label: 'Type', value: a.type, options: [{ value: 'tour', label: 'Tours' }, { value: 'hotel', label: 'Hotels' }, { value: 'event', label: 'Events' }, { value: 'visa', label: 'Visas' }, { value: 'all', label: 'All' }], onChange: set('type') }),
          el(C.RangeControl, { label: 'Number', value: a.limit, min: 1, max: 48, onChange: set('limit') }),
          el(C.RangeControl, { label: 'Columns', value: a.columns, min: 1, max: 6, onChange: set('columns') }),
          el(C.ToggleControl, { label: 'Only last minute', checked: a.lastMinute, onChange: set('lastMinute') }),
          el(C.TextControl, { label: 'Destination (slug)', value: a.destination, onChange: set('destination') }),
          el(C.SelectControl, { label: 'Order', value: a.orderby, options: [{ value: 'date', label: 'Newest' }, { value: 'departure', label: 'Next date' }, { value: 'price', label: 'Price' }, { value: 'title', label: 'Name' }, { value: 'random', label: 'Random' }], onChange: set('orderby') }))),
        el(SSR, { block: 'seeinside-travel/list', attributes: a }));
    },
    save: function () { return null; }
  });

  [['slider', 'Homepage slider', 'slides'], ['search', 'Search', 'search'], ['flights', 'Flight search', 'airplane'], ['charters', 'Charter seats', 'list-view'], ['status', 'Check booking', 'yes-alt']].forEach(function (b) {
    wp.blocks.registerBlockType('seeinside-travel/' + b[0], {
      title: 'SeeInside Travel – ' + b[1], icon: b[2], category: cat,
      edit: function () { return el(SSR, { block: 'seeinside-travel/' + b[0] }); },
      save: function () { return null; }
    });
  });
})(window.wp);
