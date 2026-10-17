/* SeeInside Travel – hero slider with WebGL effects (liquid glass trail / water ripples) and transitions.
   No dependencies. Falls back to CSS cross-fade when WebGL is not available. */
(function () {
  'use strict';

  var VS = 'attribute vec2 p;varying vec2 vUv;void main(){vUv=p*.5+.5;gl_Position=vec4(p,0.,1.);}';

  // liquid: rg = velocity, b = intensity (float textures) – advected, diffused and fading
  var FS_LIQUID = [
    'precision highp float;varying vec2 vUv;',
    'uniform sampler2D uPrev;uniform vec2 uMouse,uVel,uTexel;uniform float uRadius,uAspect,uDecay,uEnc;',
    'vec3 dec(vec4 c){return uEnc>.5?vec3(c.rg*2.-1.,c.b):c.rgb;}',
    'void main(){',
    ' vec3 f=dec(texture2D(uPrev,vUv));',
    ' vec2 b=vUv-f.xy*uTexel*5.;',
    ' vec3 a=dec(texture2D(uPrev,b));',
    ' vec3 s=(dec(texture2D(uPrev,b+vec2(uTexel.x,0.)))+dec(texture2D(uPrev,b-vec2(uTexel.x,0.)))+dec(texture2D(uPrev,b+vec2(0.,uTexel.y)))+dec(texture2D(uPrev,b-vec2(0.,uTexel.y))))*.25;',
    ' a=mix(a,s,.45)*uDecay;',
    ' vec2 d=vUv-uMouse;d.x*=uAspect;',
    ' float g=exp(-dot(d,d)/(uRadius*uRadius));',
    ' a.xy+=uVel*g;a.z+=min(length(uVel),1.)*g*.9;',
    ' a=clamp(a,vec3(-1.,-1.,0.),vec3(1.));',
    ' if(uEnc>.5){if(abs(a.x)<.012)a.x=0.;if(abs(a.y)<.012)a.y=0.;if(a.z<.012)a.z=0.;gl_FragColor=vec4(a.xy*.5+.5,a.z,1.);}else gl_FragColor=vec4(a,1.);',
    '}'
  ].join('\n');

  // ripples: r = height now, g = height before (wave equation)
  var FS_RIPPLE = [
    'precision highp float;varying vec2 vUv;',
    'uniform sampler2D uPrev;uniform vec2 uMouse,uVel,uTexel;uniform float uRadius,uAspect,uDecay,uEnc;',
    'vec2 dec(vec4 c){return uEnc>.5?c.rg*2.-1.:c.rg;}',
    'void main(){',
    ' vec2 c=dec(texture2D(uPrev,vUv));',
    ' float n=(dec(texture2D(uPrev,vUv+vec2(uTexel.x,0.))).x+dec(texture2D(uPrev,vUv-vec2(uTexel.x,0.))).x+dec(texture2D(uPrev,vUv+vec2(0.,uTexel.y))).x+dec(texture2D(uPrev,vUv-vec2(0.,uTexel.y))).x)*.5-c.y;',
    ' n*=uDecay;',
    ' vec2 d=vUv-uMouse;d.x*=uAspect;',
    ' float l=length(d);n+=min(length(uVel),1.)*smoothstep(uRadius,0.,l)*.9;',
    ' n=clamp(n,-1.,1.);',
    ' gl_FragColor=uEnc>.5?vec4(vec2(n,c.x)*.5+.5,0.,1.):vec4(n,c.x,0.,1.);',
    '}'
  ].join('\n');

  var FS_RENDER = [
    'precision highp float;varying vec2 vUv;',
    'uniform sampler2D uA,uB,uField;uniform vec2 uRes,uSizeA,uSizeB,uTexel;uniform float uP,uTrans,uMode,uStrength,uEnc,uTime,uZoomA,uZoomB,uDir;',
    'vec2 cover(vec2 uv,vec2 img,float z){float rs=uRes.x/uRes.y,ri=img.x/img.y;vec2 s=rs<ri?vec2(rs/ri,1.):vec2(1.,ri/rs);return (uv-.5)*s/z+.5;}',
    'vec3 fld(vec2 uv){vec4 c=texture2D(uField,uv);return uEnc>.5?(uMode>1.5?vec3(c.rg*2.-1.,0.):vec3(c.rg*2.-1.,c.b)):c.rgb;}',
    'float hash(vec2 p){return fract(sin(dot(p,vec2(127.1,311.7)))*43758.5453);}',
    'float noise(vec2 p){vec2 i=floor(p),f=fract(p);f=f*f*(3.-2.*f);return mix(mix(hash(i),hash(i+vec2(1,0)),f.x),mix(hash(i+vec2(0,1)),hash(i+vec2(1,1)),f.x),f.y);}',
    'float fbm(vec2 p){float v=0.,a=.5;for(int i=0;i<4;i++){v+=a*noise(p);p*=2.03;a*=.5;}return v;}',
    'vec3 img(sampler2D t,vec2 uv,vec2 size,float z,vec2 disp,float ca){',
    ' vec2 u=cover(uv,size,z);',
    ' return vec3(texture2D(t,u+disp*(1.+ca)).r,texture2D(t,u+disp).g,texture2D(t,u+disp*(1.-ca)).b);}',
    'void main(){',
    ' vec2 uv=vUv;vec2 disp=vec2(0.);float light=0.;float ca=0.;',
    ' if(uMode>.5){',
    '  vec3 f=fld(uv);',
    '  if(uMode<1.5){',
    '   vec2 o=uTexel*2.5;',
    '   float r=fld(uv+vec2(o.x,0.)).z,l=fld(uv-vec2(o.x,0.)).z,t=fld(uv+vec2(0.,o.y)).z,b=fld(uv-vec2(0.,o.y)).z;',
    '   vec2 g=vec2(r-l,t-b);',
    '   float h=smoothstep(0.,.55,f.z);',
    '   disp=(f.xy*.03+g*.32)*uStrength;',
    '   vec3 n=normalize(vec3(-g*3.5,1.));',
    '   light=(pow(max(dot(n,normalize(vec3(-.45,.55,.7))),0.),16.)*.32+smoothstep(.02,.5,length(g))*.07)*h;',
    '   ca=.04*h;',
    '  }else{',
    '   float r=fld(uv+vec2(uTexel.x,0.)).x,l=fld(uv-vec2(uTexel.x,0.)).x,t=fld(uv+vec2(0.,uTexel.y)).x,b=fld(uv-vec2(0.,uTexel.y)).x;',
    '   vec2 g=vec2(r-l,t-b);disp=g*.35*uStrength;',
    '   light=pow(max(dot(normalize(vec3(-g*10.,1.)),normalize(vec3(-.4,.6,.7))),0.),40.)*.8;',
    '  }',
    ' }',
    ' float p=uP;vec2 dA=disp,dB=disp;float zA=uZoomA,zB=uZoomB;vec2 oA=vec2(0.),oB=vec2(0.);float m=p;',
    ' if(uTrans>3.5){float n=fbm(uv*3.+uTime*.15)-.5;dA+=vec2(n,n*.6)*p*.38;dB-=vec2(n,n*.6)*(1.-p)*.38;m=smoothstep(.15,.85,p+(n*.6));}',
    ' else if(uTrans>2.5){zA*=1.+.18*p;zB*=1.18-.18*p;}',
    ' else if(uTrans>1.5){oA=vec2(p*uDir,0.);oB=vec2((p-1.)*uDir,0.);m=step(1.-p,uv.x*uDir+(uDir<0.?1.:0.));m=uDir>0.?step(1.-p,uv.x):step(uv.x,p);}',
    ' vec3 cA=img(uA,uv+oA,uSizeA,zA,dA,ca);',
    ' vec3 c=cA;',
    ' if(p>0.){vec3 cB=img(uB,uv+oB,uSizeB,zB,dB,ca);c=mix(cA,cB,clamp(m,0.,1.));}',
    ' c+=light*vec3(.95,.98,1.);',
    ' gl_FragColor=vec4(c,1.);',
    '}'
  ].join('\n');

  function compile(gl, type, src) {
    var s = gl.createShader(type); gl.shaderSource(s, src); gl.compileShader(s);
    if (!gl.getShaderParameter(s, gl.COMPILE_STATUS)) throw new Error(gl.getShaderInfoLog(s));
    return s;
  }
  function program(gl, fs) {
    var p = gl.createProgram();
    gl.attachShader(p, compile(gl, gl.VERTEX_SHADER, VS)); gl.attachShader(p, compile(gl, gl.FRAGMENT_SHADER, fs));
    gl.bindAttribLocation(p, 0, 'p'); gl.linkProgram(p);
    if (!gl.getProgramParameter(p, gl.LINK_STATUS)) throw new Error(gl.getProgramInfoLog(p));
    var u = {}, n = gl.getProgramParameter(p, gl.ACTIVE_UNIFORMS);
    for (var i = 0; i < n; i++) { var info = gl.getActiveUniform(p, i); u[info.name] = gl.getUniformLocation(p, info.name); }
    return { p: p, u: u };
  }

  /* ---------------- WebGL layer ---------------- */
  function GL(canvas, mode) {
    var opts = { alpha: false, antialias: false, premultipliedAlpha: false, preserveDrawingBuffer: false };
    var gl = canvas.getContext('webgl2', opts), v2 = !!gl;
    if (!gl) gl = canvas.getContext('webgl', opts) || canvas.getContext('experimental-webgl', opts);
    if (!gl) throw new Error('no webgl');
    this.gl = gl; this.mode = mode;

    // a float render target for the simulation (falls back to 8-bit encoding)
    var type = gl.UNSIGNED_BYTE, internal = gl.RGBA, enc = 1;
    if (v2 && gl.getExtension('EXT_color_buffer_float')) { type = gl.HALF_FLOAT; internal = gl.RGBA16F; enc = 0; }
    else if (!v2) { var hf = gl.getExtension('OES_texture_half_float'); if (hf && gl.getExtension('OES_texture_half_float_linear')) { type = hf.HALF_FLOAT_OES; enc = 0; } }
    this.enc = enc;

    var buf = gl.createBuffer(); gl.bindBuffer(gl.ARRAY_BUFFER, buf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    gl.enableVertexAttribArray(0); gl.vertexAttribPointer(0, 2, gl.FLOAT, false, 0, 0);

    this.render = program(gl, FS_RENDER);
    this.sim = mode === 'none' ? null : program(gl, mode === 'ripple' ? FS_RIPPLE : FS_LIQUID);
    this.fbType = type; this.fbInternal = internal;
    this.tex = {}; this.sizes = {};
    this.blank = this.texture(1, 1, new Uint8Array([0, 0, 0, 255]));
    if (this.sim) this.allocSim(448, 252);
  }
  GL.prototype.texture = function (w, h, data, type, internal) {
    var gl = this.gl, t = gl.createTexture();
    gl.bindTexture(gl.TEXTURE_2D, t);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MIN_FILTER, gl.LINEAR); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_MAG_FILTER, gl.LINEAR);
    gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_S, gl.CLAMP_TO_EDGE); gl.texParameteri(gl.TEXTURE_2D, gl.TEXTURE_WRAP_T, gl.CLAMP_TO_EDGE);
    gl.texImage2D(gl.TEXTURE_2D, 0, internal || gl.RGBA, w, h, 0, gl.RGBA, type || gl.UNSIGNED_BYTE, data || null);
    return t;
  };
  GL.prototype.allocSim = function (w, h) {
    var gl = this.gl, self = this;
    this.simW = w; this.simH = h;
    var init = null;
    if (this.enc) { init = new Uint8Array(w * h * 4); for (var i = 0; i < init.length; i += 4) { init[i] = 128; init[i + 1] = 128; init[i + 2] = this.mode === 'ripple' ? 0 : 0; init[i + 3] = 255; } }
    this.fb = [0, 1].map(function () {
      var t = self.texture(w, h, init, self.fbType, self.fbInternal), f = gl.createFramebuffer();
      gl.bindFramebuffer(gl.FRAMEBUFFER, f); gl.framebufferTexture2D(gl.FRAMEBUFFER, gl.COLOR_ATTACHMENT0, gl.TEXTURE_2D, t, 0);
      return { t: t, f: f };
    });
    if (gl.checkFramebufferStatus(gl.FRAMEBUFFER) !== gl.FRAMEBUFFER_COMPLETE) {
      if (this.enc) throw new Error('framebuffer');
      this.enc = 1; this.fbType = gl.UNSIGNED_BYTE; this.fbInternal = gl.RGBA; return this.allocSim(w, h);
    }
    gl.bindFramebuffer(gl.FRAMEBUFFER, null);
  };
  GL.prototype.setImage = function (i, image) {
    var gl = this.gl;
    var t = this.texture(1, 1, new Uint8Array([0, 0, 0, 255]));
    gl.bindTexture(gl.TEXTURE_2D, t);
    gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, true);
    gl.texImage2D(gl.TEXTURE_2D, 0, gl.RGBA, gl.RGBA, gl.UNSIGNED_BYTE, image);
    gl.pixelStorei(gl.UNPACK_FLIP_Y_WEBGL, false);   // throws on cross-origin images
    this.tex[i] = t; this.sizes[i] = [image.naturalWidth, image.naturalHeight];
  };
  GL.prototype.step = function (mouse, vel, radius, decay, aspect) {
    if (!this.sim) return;
    var gl = this.gl, s = this.sim, src = this.fb[0], dst = this.fb[1];
    gl.useProgram(s.p);
    gl.bindFramebuffer(gl.FRAMEBUFFER, dst.f); gl.viewport(0, 0, this.simW, this.simH);
    gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, src.t); gl.uniform1i(s.u.uPrev, 0);
    gl.uniform2f(s.u.uMouse, mouse[0], mouse[1]); gl.uniform2f(s.u.uVel, vel[0], vel[1]);
    gl.uniform2f(s.u.uTexel, 1 / this.simW, 1 / this.simH);
    gl.uniform1f(s.u.uRadius, radius); gl.uniform1f(s.u.uAspect, aspect); gl.uniform1f(s.u.uDecay, decay); gl.uniform1f(s.u.uEnc, this.enc);
    gl.drawArrays(gl.TRIANGLES, 0, 3);
    this.fb = [dst, src];
  };
  GL.prototype.draw = function (w, h, a, b, st) {
    var gl = this.gl, r = this.render;
    gl.bindFramebuffer(gl.FRAMEBUFFER, null); gl.viewport(0, 0, w, h); gl.useProgram(r.p);
    gl.activeTexture(gl.TEXTURE0); gl.bindTexture(gl.TEXTURE_2D, this.tex[a] || this.blank); gl.uniform1i(r.u.uA, 0);
    gl.activeTexture(gl.TEXTURE1); gl.bindTexture(gl.TEXTURE_2D, this.tex[b] || this.tex[a] || this.blank); gl.uniform1i(r.u.uB, 1);
    gl.activeTexture(gl.TEXTURE2); gl.bindTexture(gl.TEXTURE_2D, this.sim ? this.fb[0].t : this.blank); gl.uniform1i(r.u.uField, 2);
    var sa = this.sizes[a] || [w, h], sb = this.sizes[b] || sa;
    gl.uniform2f(r.u.uRes, w, h); gl.uniform2f(r.u.uSizeA, sa[0], sa[1]); gl.uniform2f(r.u.uSizeB, sb[0], sb[1]);
    gl.uniform2f(r.u.uTexel, 1 / (this.simW || 448), 1 / (this.simH || 252));
    gl.uniform1f(r.u.uP, st.p); gl.uniform1f(r.u.uTrans, st.trans); gl.uniform1f(r.u.uMode, this.sim ? (this.mode === 'ripple' ? 2 : 1) : 0);
    gl.uniform1f(r.u.uStrength, st.strength); gl.uniform1f(r.u.uEnc, this.enc); gl.uniform1f(r.u.uTime, st.time);
    gl.uniform1f(r.u.uZoomA, st.zoomA); gl.uniform1f(r.u.uZoomB, st.zoomB); gl.uniform1f(r.u.uDir, st.dir);
    gl.drawArrays(gl.TRIANGLES, 0, 3);
  };

  /* ---------------- slider ---------------- */
  var TRANS = { none: 0, fade: 1, slide: 2, zoom: 3, liquid: 4 };

  function Hero(el) {
    var cfg = {}; try { cfg = JSON.parse(el.getAttribute('data-sta-hero') || '{}'); } catch (e) {}
    var self = this;
    this.el = el; this.cfg = cfg;
    this.bgs = [].slice.call(el.querySelectorAll('.sta-hero-bg'));
    this.slides = [].slice.call(el.querySelectorAll('.sta-hero-slide'));
    this.n = this.slides.length; this.i = 0; this.prev = 0;
    this.progressEl = el.querySelector('.sta-hero-progress i');
    this.vertical = /vertical/.test(el.className) && window.innerWidth > 767;
    this.elapsed = 0; this.paused = false; this.visible = true; this.last = performance.now();
    this.trans = { p: 0, active: false, start: 0 };
    this.mouse = [.5, .5]; this.vel = [0, 0]; this.lastMove = 0; this.prevMouse = null;

    if (el.classList.contains('is-text-letters')) this.splitLetters();

    var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;
    var touch = window.matchMedia && matchMedia('(hover: none)').matches;
    var wantGL = !reduce && (cfg.transition !== 'none' || cfg.mouse !== 'none') && !(touch && !cfg.mobile);
    if (wantGL) {
      try { this.gl = new GL(el.querySelector('.sta-hero-gl'), (touch && !cfg.mobile) || reduce ? 'none' : (cfg.mouse || 'none')); }
      catch (e) { this.gl = null; }
    }
    if (this.gl) this.loadTextures();

    var prev = el.querySelector('.sta-hero-prev'), next = el.querySelector('.sta-hero-next');
    prev && prev.addEventListener('click', function () { self.go(self.i - 1, -1); });
    next && next.addEventListener('click', function () { self.go(self.i + 1, 1); });
    [].slice.call(el.querySelectorAll('.sta-hero-pag button')).forEach(function (b, k) { b.addEventListener('click', function () { self.go(k, k > self.i ? 1 : -1); }); });
    if (cfg.pauseHover) { el.addEventListener('mouseenter', function () { self.paused = true; }); el.addEventListener('mouseleave', function () { self.paused = false; }); }
    if (cfg.keyboard) document.addEventListener('keydown', function (e) {
      if (!self.visible || /input|textarea|select/i.test((e.target || {}).tagName)) return;
      if (e.key === 'ArrowRight' || e.key === 'ArrowDown') self.go(self.i + 1, 1);
      if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') self.go(self.i - 1, -1);
    });
    // swipe
    var x0 = null, y0 = null;
    el.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; y0 = e.touches[0].clientY; }, { passive: true });
    el.addEventListener('touchend', function (e) {
      if (x0 === null) return;
      var dx = e.changedTouches[0].clientX - x0, dy = e.changedTouches[0].clientY - y0;
      if (Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) self.go(self.i + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
      x0 = null;
    }, { passive: true });
    // pointer for the effects
    el.addEventListener('pointermove', function (e) {
      var r = el.getBoundingClientRect(), x = (e.clientX - r.left) / r.width, y = 1 - (e.clientY - r.top) / r.height;
      if (self.prevMouse) { self.vel = [(x - self.prevMouse[0]) * 22, (y - self.prevMouse[1]) * 22]; }
      self.prevMouse = [x, y]; self.mouse = [x, y]; self.lastMove = performance.now();
    }, { passive: true });
    el.addEventListener('pointerleave', function () { self.prevMouse = null; self.vel = [0, 0]; });
    if ('IntersectionObserver' in window) new IntersectionObserver(function (en) { self.visible = en[0].isIntersecting; }).observe(el);
    document.addEventListener('visibilitychange', function () { self.last = performance.now(); });

    requestAnimationFrame(function loop(t) { self.tick(t); requestAnimationFrame(loop); });
  }

  Hero.prototype.splitLetters = function () {
    this.slides.forEach(function (s) {
      var h = s.querySelector('.sta-hero-title'); if (!h) return;
      var text = h.textContent, i = 0;
      h.setAttribute('aria-label', text);
      h.innerHTML = text.split(/(\s+)/).map(function (w) {
        if (/^\s+$/.test(w)) return w;
        return '<span style="display:inline-block;white-space:nowrap" aria-hidden="true">' + w.split('').map(function (ch) { return '<span class="sta-ch" style="--i:' + (i++) + '">' + ch.replace(/&/g, '&amp;').replace(/</g, '&lt;') + '</span>'; }).join('') + '</span>';
      }).join('');
    });
  };

  Hero.prototype.loadTextures = function () {
    var self = this;
    this.bgs.forEach(function (bg, k) {
      var src = bg.getAttribute('data-src'); if (!src) return;
      var img = new Image();
      try { if (new URL(src, location.href).origin !== location.origin) img.crossOrigin = 'anonymous'; } catch (e) {}
      img.onload = function () {
        try {
          self.gl.setImage(k, img);
          if (k === self.i) { self.el.classList.add('has-gl'); self.resize(true); }
        } catch (e) { self.disableGL(); }
      };
      img.onerror = function () { if (k === self.i) self.disableGL(); };
      img.src = src;
    });
  };

  Hero.prototype.disableGL = function () { this.gl = null; this.el.classList.remove('has-gl'); };

  Hero.prototype.resize = function (force) {
    var c = this.el.querySelector('.sta-hero-gl'), dpr = Math.min(window.devicePixelRatio || 1, 1.5);
    var w = Math.round(this.el.clientWidth * dpr), h = Math.round(this.el.clientHeight * dpr);
    if (force || c.width !== w || c.height !== h) { c.width = w; c.height = h; }
    return [w, h];
  };

  Hero.prototype.go = function (to, dir) {
    if (this.n < 2) return;
    to = (to + this.n) % this.n;
    if (to === this.i) return;
    var self = this, from = this.i, out = this.slides[from], inn = this.slides[to];
    this.prev = from; this.i = to; this.dir = dir || 1; this.elapsed = 0;
    out.classList.remove('is-active'); out.classList.add('is-leaving'); out.setAttribute('aria-hidden', 'true');
    setTimeout(function () { out.classList.remove('is-leaving'); }, 650);
    setTimeout(function () { inn.classList.add('is-active'); inn.removeAttribute('aria-hidden'); }, Math.min(500, this.cfg.duration * .35));
    this.bgs.forEach(function (b, k) { b.classList.toggle('is-active', k === to); });
    [].slice.call(this.el.querySelectorAll('.sta-hero-pag button')).forEach(function (b, k) { b.classList.toggle('is-active', k === to); });
    var cur = this.el.querySelector('.sta-hero-cur'); if (cur) cur.textContent = ('0' + (to + 1)).slice(-2);
    this.trans = { active: true, start: performance.now(), p: 0, from: from, to: to };
    if (this.cfg.transition === 'none') this.trans.active = false;
  };

  Hero.prototype.tick = function (t) {
    var dt = Math.min(64, t - this.last); this.last = t;
    var cfg = this.cfg;
    // autoplay + progress line
    if (cfg.autoplay > 0 && this.n > 1 && this.visible && !document.hidden) {
      if (!this.paused) this.elapsed += dt;
      var total = cfg.autoplay * 1000, f = Math.min(1, this.elapsed / total);
      if (this.progressEl) {
        var horiz = getComputedStyle(this.progressEl.parentNode).width > getComputedStyle(this.progressEl.parentNode).height;
        horiz = this.progressEl.parentNode.clientWidth > this.progressEl.parentNode.clientHeight;
        this.progressEl.style[horiz ? 'width' : 'height'] = (f * 100) + '%';
        this.progressEl.style[horiz ? 'height' : 'width'] = '100%';
      }
      if (f >= 1) this.go(this.i + 1, 1);
    }
    if (!this.gl || !this.visible || !this.el.classList.contains('has-gl')) return;

    var size = this.resize(false), aspect = size[0] / size[1];
    // simulation runs a few seconds after the last movement
    var active = t - this.lastMove < 4500;
    if (active) {
      var sz = cfg.size || 1, mode = this.gl.mode;
      this.gl.step(this.mouse, this.vel, (mode === 'ripple' ? .035 : .11) * sz, mode === 'ripple' ? .985 : .965, aspect);
      if (mode === 'ripple') this.gl.step(this.mouse, [0, 0], .035 * sz, .985, aspect);
      this.vel = [this.vel[0] * .6, this.vel[1] * .6];
    }
    // transition progress
    var tr = this.trans, p = 0, a = this.i, b = this.i;
    if (tr.active) {
      p = Math.min(1, (t - tr.start) / (cfg.duration || 1300));
      p = p < .5 ? 2 * p * p : 1 - Math.pow(-2 * p + 2, 2) / 2;   // ease in-out
      a = tr.from; b = tr.to;
      if (p >= 1) { tr.active = false; p = 0; a = b = this.i; }
    }
    var kb = cfg.kenBurns ? 1 + .09 * Math.min(1, this.elapsed / ((cfg.autoplay || 10) * 1000)) : 1;
    this.gl.draw(size[0], size[1], a, b, {
      p: p, trans: TRANS[cfg.transition] || 1, strength: cfg.strength || 1, time: t / 1000,
      zoomA: tr.active && cfg.kenBurns ? 1.09 : kb, zoomB: cfg.kenBurns && tr.active ? 1 : 1, dir: this.dir || 1,
    });
  };

  function boot() { [].slice.call(document.querySelectorAll('[data-sta-hero]')).forEach(function (el) { if (!el.__sta) { el.__sta = new Hero(el); } }); }
  if (document.readyState !== 'loading') boot(); else document.addEventListener('DOMContentLoaded', boot);
  // Elementor editor preview
  window.addEventListener('elementor/frontend/init', function () {
    if (window.elementorFrontend && elementorFrontend.hooks) elementorFrontend.hooks.addAction('frontend/element_ready/sta-slider.default', boot);
  });
})();
