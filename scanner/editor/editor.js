document.addEventListener('DOMContentLoaded', async () => {
  const bgImg = document.getElementById('editor-bg-img');
  const overlay = document.getElementById('scanext-snipper-overlay');

  const data = await chrome.storage.local.get('desktopCapture');
  if (!data || !data.desktopCapture) {
    alert('Ingen skjermdata funnet.');
    window.close();
    return;
  }

  const pristineImg = new Image();
  pristineImg.onload = () => {
    bgImg.src = data.desktopCapture;
    initEditorSnipper(pristineImg);
  };
  pristineImg.src = data.desktopCapture;

  function initEditorSnipper(imgSource) {
    let isDrawing = false;
    let startX = 0;
    let startY = 0;
    let currentBox = null;
    let currentToolbar = null;
    let finalRect = null;

    function cleanup() {
      window.close();
    }

    function onKeyDown(e) {
      if (e.key === 'Escape') {
        cleanup();
      }
    }
    document.addEventListener('keydown', onKeyDown);

    overlay.addEventListener('pointerdown', (e) => {
      if (e.target !== overlay) return;

      if (currentToolbar) {
        currentToolbar.remove();
        currentToolbar = null;
      }
      if (currentBox) {
        currentBox.remove();
        currentBox = null;
      }
      const guide = document.getElementById('scanext-snip-guide');
      if (guide) guide.style.opacity = '0';

      isDrawing = true;
      startX = e.clientX;
      startY = e.clientY;

      overlay.style.background = 'transparent';

      currentBox = document.createElement('div');
      currentBox.className = 'scanext-snip-selection';
      currentBox.style.cssText = `position:absolute!important;border:2px solid #38bdf8!important;box-shadow:0 0 0 99999px rgba(0,0,0,0.18)!important;pointer-events:none!important;border-radius:2px!important;background:transparent!important;left:${startX}px!important;top:${startY}px!important;width:0px!important;height:0px!important;box-sizing:border-box!important;`;

      const dim = document.createElement('div');
      dim.className = 'scanext-snip-dimensions';
      dim.style.cssText = 'position:absolute;top:-24px;left:0;background:#0f172a;color:#38bdf8;font-size:11px;font-weight:700;padding:2px 6px;border-radius:4px;border:1px solid rgba(56,189,248,0.4);white-space:nowrap;font-family:-apple-system,sans-serif;pointer-events:none;';
      dim.textContent = '0 x 0 px';
      currentBox.appendChild(dim);

      overlay.appendChild(currentBox);

      try { overlay.setPointerCapture(e.pointerId); } catch (err) {}
    });

    window.addEventListener('pointermove', (e) => {
      if (!isDrawing || !currentBox) return;

      const currentX = e.clientX;
      const currentY = e.clientY;

      const x = Math.min(startX, currentX);
      const y = Math.min(startY, currentY);
      const w = Math.abs(currentX - startX);
      const h = Math.abs(currentY - startY);

      currentBox.style.left = `${x}px`;
      currentBox.style.top = `${y}px`;
      currentBox.style.width = `${w}px`;
      currentBox.style.height = `${h}px`;

      const dim = currentBox.querySelector('.scanext-snip-dimensions');
      if (dim) {
        dim.textContent = `${Math.round(w)} × ${Math.round(h)} px`;
        if (y < 30) {
          dim.style.top = '6px';
          dim.style.left = '6px';
        } else {
          dim.style.top = '-24px';
          dim.style.left = '0px';
        }
      }
    });

    window.addEventListener('pointerup', (e) => {
      if (!isDrawing || !currentBox) return;
      isDrawing = false;

      try { overlay.releasePointerCapture(e.pointerId); } catch (err) {}

      const currentX = e.clientX;
      const currentY = e.clientY;

      const x = Math.min(startX, currentX);
      const y = Math.min(startY, currentY);
      const w = Math.abs(currentX - startX);
      const h = Math.abs(currentY - startY);

      if (w < 12 || h < 12) {
        currentBox.remove();
        currentBox = null;
        overlay.style.background = 'rgba(0,0,0,0.18)';
        return;
      }

      currentBox.style.pointerEvents = 'auto';
      finalRect = { x, y, w, h };

      // Initialize Interactive Canvas Inside Selection Box Wrap
      const canvasWrap = document.createElement('div');
      canvasWrap.className = 'scanext-snip-canvas-wrap';
      canvasWrap.style.cssText = 'position:absolute!important;top:0!important;left:0!important;width:100%!important;height:100%!important;overflow:hidden!important;pointer-events:auto!important;';

      const snipCanvas = document.createElement('canvas');
      snipCanvas.className = 'scanext-snip-canvas';
      snipCanvas.style.cssText = 'position:absolute!important;top:0!important;left:0!important;width:100%!important;height:100%!important;display:block!important;box-sizing:border-box!important;touch-action:none!important;cursor:crosshair!important;';
      canvasWrap.appendChild(snipCanvas);
      currentBox.appendChild(canvasWrap);

      const dpr = window.devicePixelRatio || 1;
      const snipCtx = snipCanvas.getContext('2d');
      const undoStack = [];
      let activeEditorTool = 'none'; // 'none' | 'blur' | 'pen' | 'highlighter'
      let activeBlurMode = 'box'; // 'box' | 'brush'
      let activeBlurBrushRadius = 26; // 26px radius = 52px wide brush
      let activePenColor = '#ef4444';
      let activeHighlighterColor = 'rgba(254, 240, 138, 0.70)';
      let isEditorDrawing = false;
      let editStartX = 0;
      let editStartY = 0;
      let penPoints = [];
      let strokeSnapshot = null;
      let previewBox = null;

      // Draw pristine crop with exact 1:1 pixel mapping
      function renderPristineCrop(imgSrc) {
        if (!imgSrc || !imgSrc.naturalWidth) return;
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const scaleX = imgSrc.naturalWidth / vw;
        const scaleY = imgSrc.naturalHeight / vh;

        const cropW = Math.max(1, Math.round(finalRect.w * scaleX));
        const cropH = Math.max(1, Math.round(finalRect.h * scaleY));
        snipCanvas.width = cropW;
        snipCanvas.height = cropH;

        const srcX = Math.round(finalRect.x * scaleX);
        const srcY = Math.round(finalRect.y * scaleY);
        snipCtx.drawImage(imgSrc, srcX, srcY, cropW, cropH, 0, 0, cropW, cropH);
      }

      renderPristineCrop(imgSource);

      // Add 8 Interactive Handles
      const handles = ['nw', 'ne', 'se', 'sw', 'n', 's', 'e', 'w'];
      handles.forEach(dir => {
        const hEl = document.createElement('div');
        hEl.className = `scanext-snip-handle scanext-snip-handle-${dir}`;
        hEl.dataset.dir = dir;
        currentBox.appendChild(hEl);

        hEl.addEventListener('pointerdown', (evt) => {
          evt.stopPropagation();
          evt.preventDefault();
          try { hEl.setPointerCapture(evt.pointerId); } catch (e) {}

          const startMouseX = evt.clientX;
          const startMouseY = evt.clientY;
          const initX = finalRect.x;
          const initY = finalRect.y;
          const initW = finalRect.w;
          const initH = finalRect.h;

          function onHandleMove(mEvt) {
            mEvt.stopPropagation();
            mEvt.preventDefault();
            const dx = mEvt.clientX - startMouseX;
            const dy = mEvt.clientY - startMouseY;

            let newX = initX;
            let newY = initY;
            let newW = initW;
            let newH = initH;

            if (dir.includes('e')) newW = Math.max(20, initW + dx);
            if (dir.includes('s')) newH = Math.max(20, initH + dy);
            if (dir.includes('w')) {
              const proposedW = initW - dx;
              if (proposedW >= 20) {
                newW = proposedW;
                newX = initX + dx;
              }
            }
            if (dir.includes('n')) {
              const proposedH = initH - dy;
              if (proposedH >= 20) {
                newH = proposedH;
                newY = initY + dy;
              }
            }

            finalRect = { x: newX, y: newY, w: newW, h: newH };
            currentBox.style.left = `${newX}px`;
            currentBox.style.top = `${newY}px`;
            currentBox.style.width = `${newW}px`;
            currentBox.style.height = `${newH}px`;

            const dimBadge = currentBox.querySelector('.scanext-snip-dimensions');
            if (dimBadge) {
              dimBadge.textContent = `${Math.round(newW)} × ${Math.round(newH)} px`;
            }

            renderPristineCrop(imgSource);
            positionToolbar();
          }

          function onHandleUp(uEvt) {
            try { hEl.releasePointerCapture(uEvt.pointerId); } catch (e) {}
            window.removeEventListener('pointermove', onHandleMove);
            window.removeEventListener('pointerup', onHandleUp);
          }

          window.addEventListener('pointermove', onHandleMove);
          window.addEventListener('pointerup', onHandleUp);
        });
      });

      function pushUndo() {
        if (undoStack.length >= 25) undoStack.shift();
        undoStack.push(snipCtx.getImageData(0, 0, snipCanvas.width, snipCanvas.height));
        updateUndoButton();
      }

      function updateUndoButton() {
        const undoBtn = currentToolbar.querySelector('#scanext-snip-undo');
        if (undoBtn) {
          undoBtn.disabled = (undoStack.length === 0);
        }
      }

      function applyMosaicBlur(ctx, rx, ry, rw, rh) {
        if (rw < 2 || rh < 2) return;
        const factor = Math.max(8, Math.round(Math.min(rw, rh) / 4));
        const sw = Math.max(1, Math.floor(rw / factor));
        const sh = Math.max(1, Math.floor(rh / factor));

        const subData = ctx.getImageData(rx, ry, rw, rh);
        const tempCanvas = document.createElement('canvas');
        tempCanvas.width = rw;
        tempCanvas.height = rh;
        tempCanvas.getContext('2d').putImageData(subData, 0, 0);

        const offCanvas = document.createElement('canvas');
        offCanvas.width = sw;
        offCanvas.height = sh;
        const offCtx = offCanvas.getContext('2d');
        offCtx.drawImage(tempCanvas, 0, 0, sw, sh);

        ctx.save();
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(offCanvas, 0, 0, sw, sh, rx, ry, rw, rh);
        ctx.strokeStyle = 'rgba(0, 0, 0, 0.12)';
        ctx.lineWidth = 1;
        ctx.strokeRect(rx + 0.5, ry + 0.5, rw - 1, rh - 1);
        ctx.restore();
      }

      function applyBrushBlur(ctx, points, radius) {
        if (!points || points.length === 0) return;
        const w = ctx.canvas.width;
        const h = ctx.canvas.height;
        if (w < 2 || h < 2) return;

        const factor = Math.max(8, Math.round(radius / 2.2));
        const sw = Math.max(1, Math.floor(w / factor));
        const sh = Math.max(1, Math.floor(h / factor));

        const offCanvas = document.createElement('canvas');
        offCanvas.width = sw;
        offCanvas.height = sh;
        const offCtx = offCanvas.getContext('2d');
        offCtx.drawImage(ctx.canvas, 0, 0, sw, sh);

        const mosaicCanvas = document.createElement('canvas');
        mosaicCanvas.width = w;
        mosaicCanvas.height = h;
        const mosaicCtx = mosaicCanvas.getContext('2d');
        mosaicCtx.imageSmoothingEnabled = false;
        mosaicCtx.drawImage(offCanvas, 0, 0, sw, sh, 0, 0, w, h);

        const maskCanvas = document.createElement('canvas');
        maskCanvas.width = w;
        maskCanvas.height = h;
        const maskCtx = maskCanvas.getContext('2d');

        maskCtx.strokeStyle = '#000000';
        maskCtx.fillStyle = '#000000';
        maskCtx.lineWidth = radius * 2;
        maskCtx.lineCap = 'round';
        maskCtx.lineJoin = 'round';

        if (points.length === 1) {
          maskCtx.beginPath();
          maskCtx.arc(points[0].x, points[0].y, radius, 0, Math.PI * 2);
          maskCtx.fill();
        } else if (points.length === 2) {
          maskCtx.beginPath();
          maskCtx.moveTo(points[0].x, points[0].y);
          maskCtx.lineTo(points[1].x, points[1].y);
          maskCtx.stroke();
        } else {
          maskCtx.beginPath();
          maskCtx.moveTo(points[0].x, points[0].y);
          for (let i = 1; i < points.length - 1; i++) {
            const xc = (points[i].x + points[i + 1].x) / 2;
            const yc = (points[i].y + points[i + 1].y) / 2;
            maskCtx.quadraticCurveTo(points[i].x, points[i].y, xc, yc);
          }
          maskCtx.lineTo(points[points.length - 1].x, points[points.length - 1].y);
          maskCtx.stroke();
        }

        maskCtx.globalCompositeOperation = 'source-in';
        maskCtx.drawImage(mosaicCanvas, 0, 0);

        ctx.save();
        ctx.drawImage(maskCanvas, 0, 0);
        ctx.restore();
      }

      // Pointer events on interactive canvas
      snipCanvas.addEventListener('pointerdown', (e) => {
        if (activeEditorTool === 'none') return;
        e.stopPropagation();
        e.preventDefault();
        isEditorDrawing = true;
        try { snipCanvas.setPointerCapture(e.pointerId); } catch (err) {}

        const rect = snipCanvas.getBoundingClientRect();
        editStartX = (e.clientX - rect.left) * (snipCanvas.width / rect.width);
        editStartY = (e.clientY - rect.top) * (snipCanvas.height / rect.height);

        if (activeEditorTool === 'pen' || activeEditorTool === 'highlighter') {
          strokeSnapshot = snipCtx.getImageData(0, 0, snipCanvas.width, snipCanvas.height);
          pushUndo();
          penPoints = [{ x: editStartX, y: editStartY }];
        } else if (activeEditorTool === 'blur') {
          if (activeBlurMode === 'brush') {
            strokeSnapshot = snipCtx.getImageData(0, 0, snipCanvas.width, snipCanvas.height);
            pushUndo();
            penPoints = [{ x: editStartX, y: editStartY }];
          } else {
            previewBox = document.createElement('div');
            previewBox.className = 'scanext-snip-preview-box';
            currentBox.appendChild(previewBox);
          }
        }
      });

      snipCanvas.addEventListener('pointermove', (e) => {
        if (!isEditorDrawing) return;
        e.stopPropagation();
        e.preventDefault();

        const rect = snipCanvas.getBoundingClientRect();
        const curX = (e.clientX - rect.left) * (snipCanvas.width / rect.width);
        const curY = (e.clientY - rect.top) * (snipCanvas.height / rect.height);

        if (activeEditorTool === 'pen' && strokeSnapshot) {
          penPoints.push({ x: curX, y: curY });
          snipCtx.putImageData(strokeSnapshot, 0, 0);

          snipCtx.save();
          snipCtx.strokeStyle = activePenColor;
          snipCtx.lineWidth = Math.max(2.5, 3 * dpr);
          snipCtx.lineCap = 'round';
          snipCtx.lineJoin = 'round';
          snipCtx.beginPath();
          snipCtx.moveTo(penPoints[0].x, penPoints[0].y);

          if (penPoints.length === 2) {
            snipCtx.lineTo(penPoints[1].x, penPoints[1].y);
          } else {
            for (let i = 1; i < penPoints.length - 1; i++) {
              const xc = (penPoints[i].x + penPoints[i + 1].x) / 2;
              const yc = (penPoints[i].y + penPoints[i + 1].y) / 2;
              snipCtx.quadraticCurveTo(penPoints[i].x, penPoints[i].y, xc, yc);
            }
            snipCtx.lineTo(penPoints[penPoints.length - 1].x, penPoints[penPoints.length - 1].y);
          }
          snipCtx.stroke();
          snipCtx.restore();

        } else if (activeEditorTool === 'highlighter' && strokeSnapshot) {
          penPoints.push({ x: curX, y: curY });
          snipCtx.putImageData(strokeSnapshot, 0, 0);

          snipCtx.save();
          snipCtx.globalCompositeOperation = 'multiply';
          snipCtx.strokeStyle = activeHighlighterColor;
          snipCtx.lineWidth = Math.max(14, 18 * dpr);
          snipCtx.lineCap = 'round';
          snipCtx.lineJoin = 'round';
          snipCtx.beginPath();
          snipCtx.moveTo(penPoints[0].x, penPoints[0].y);

          if (penPoints.length === 2) {
            snipCtx.lineTo(penPoints[1].x, penPoints[1].y);
          } else {
            for (let i = 1; i < penPoints.length - 1; i++) {
              const xc = (penPoints[i].x + penPoints[i + 1].x) / 2;
              const yc = (penPoints[i].y + penPoints[i + 1].y) / 2;
              snipCtx.quadraticCurveTo(penPoints[i].x, penPoints[i].y, xc, yc);
            }
            snipCtx.lineTo(penPoints[penPoints.length - 1].x, penPoints[penPoints.length - 1].y);
          }
          snipCtx.stroke();
          snipCtx.restore();

        } else if (activeEditorTool === 'blur') {
          if (activeBlurMode === 'brush' && strokeSnapshot) {
            penPoints.push({ x: curX, y: curY });
            snipCtx.putImageData(strokeSnapshot, 0, 0);
            applyBrushBlur(snipCtx, penPoints, Math.max(10, activeBlurBrushRadius * dpr));
          } else if (previewBox) {
            const cssStartX = Math.min(e.clientX - rect.left, editStartX * (rect.width / snipCanvas.width));
            const cssStartY = Math.min(e.clientY - rect.top, editStartY * (rect.height / snipCanvas.height));
            const cssW = Math.abs((e.clientX - rect.left) - (editStartX * (rect.width / snipCanvas.width)));
            const cssH = Math.abs((e.clientY - rect.top) - (editStartY * (rect.height / snipCanvas.height)));

            previewBox.style.left = `${cssStartX}px`;
            previewBox.style.top = `${cssStartY}px`;
            previewBox.style.width = `${cssW}px`;
            previewBox.style.height = `${cssH}px`;
          }
        }
      });

      snipCanvas.addEventListener('pointerup', (e) => {
        if (!isEditorDrawing) return;
        isEditorDrawing = false;
        e.stopPropagation();

        try { snipCanvas.releasePointerCapture(e.pointerId); } catch (err) {}

        if (activeEditorTool === 'pen' || activeEditorTool === 'highlighter') {
          strokeSnapshot = null;
          penPoints = [];
        } else if (activeEditorTool === 'blur') {
          if (activeBlurMode === 'brush') {
            strokeSnapshot = null;
            penPoints = [];
          } else {
            if (previewBox) {
              previewBox.remove();
              previewBox = null;
            }
            const rect = snipCanvas.getBoundingClientRect();
            const curX = (e.clientX - rect.left) * (snipCanvas.width / rect.width);
            const curY = (e.clientY - rect.top) * (snipCanvas.height / rect.height);

            const rx = Math.min(editStartX, curX);
            const ry = Math.min(editStartY, curY);
            const rw = Math.abs(curX - editStartX);
            const rh = Math.abs(curY - editStartY);

            if (rw >= 4 && rh >= 4) {
              pushUndo();
              applyMosaicBlur(snipCtx, Math.round(rx), Math.round(ry), Math.round(rw), Math.round(rh));
            }
          }
        }
      });

      // Render Floating Toolbar
      currentToolbar = document.createElement('div');
      currentToolbar.className = 'scanext-snip-toolbar';

      function positionToolbar() {
        if (!currentToolbar || !finalRect) return;
        let toolbarTop = finalRect.y + finalRect.h + 10;
        if (toolbarTop + 55 > window.innerHeight) {
          if (finalRect.y - 52 >= 10) {
            toolbarTop = finalRect.y - 52;
          } else {
            toolbarTop = Math.max(10, finalRect.y + finalRect.h - 55);
          }
        }
        toolbarTop = Math.max(10, Math.min(window.innerHeight - 55, toolbarTop));

        let toolbarLeft = Math.round(finalRect.x + (finalRect.w / 2) - 250);
        toolbarLeft = Math.max(12, Math.min(window.innerWidth - 530, toolbarLeft));

        currentToolbar.style.top = `${toolbarTop}px`;
        currentToolbar.style.left = `${toolbarLeft}px`;
      }

      currentToolbar.style.cssText = `position:fixed!important;top:0px!important;left:0px!important;z-index:2147483647!important;background:#0f172a!important;border:1px solid rgba(255,255,255,0.25)!important;border-radius:8px!important;padding:5px 8px!important;display:flex!important;align-items:center!important;gap:5px!important;box-shadow:0 12px 30px rgba(0,0,0,0.75)!important;pointer-events:auto!important;box-sizing:border-box!important;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif!important;animation:none!important;`;
      positionToolbar();

      currentToolbar.innerHTML = `
        <button type="button" class="scanext-snip-btn scanext-snip-btn-primary" id="scanext-snip-copy-img" title="Kopier bilde til utklippstavlen (Cmd+V / Ctrl+V)">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
            <circle cx="9" cy="9" r="2"></circle>
            <path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"></path>
          </svg>
          <span>Kopier Bilde</span>
        </button>

        <button type="button" class="scanext-snip-btn" id="scanext-snip-tool-blur" title="Sladd personopplysninger">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <rect width="18" height="18" x="3" y="3" rx="2" ry="2"></rect>
            <line x1="3" y1="9" x2="21" y2="9"></line>
            <line x1="3" y1="15" x2="21" y2="15"></line>
            <line x1="9" y1="3" x2="9" y2="21"></line>
            <line x1="15" y1="3" x2="15" y2="21"></line>
          </svg>
          <span>Sladd</span>
        </button>

        <button type="button" class="scanext-snip-btn" id="scanext-snip-tool-pen" title="Tegn med penn">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"></path>
          </svg>
          <span>Penn</span>
        </button>

        <button type="button" class="scanext-snip-btn" id="scanext-snip-tool-highlighter" title="Marker tekst med gjennomsiktig tusj">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="m9 11-6 6v3h3l6-6"></path>
            <path d="m22 12-4.6 4.6a2 2 0 0 1-2.8 0l-5.2-5.2a2 2 0 0 1 0-2.8L14 4"></path>
          </svg>
          <span>Tusj</span>
        </button>

        <div id="scanext-snip-colors" class="scanext-snip-color-bar" style="display:none;"></div>

        <button type="button" class="scanext-snip-btn" id="scanext-snip-undo" title="Angre siste tegning/sladd" disabled>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M3 7v6h6"></path>
            <path d="M21 17a9 9 0 0 0-9-9 9 9 0 0 0-6 2.3L3 13"></path>
          </svg>
          <span>Angre</span>
        </button>

        <button type="button" class="scanext-snip-btn" id="scanext-snip-download" title="Lagre bilde som PNG">
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">
            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
            <polyline points="7 10 12 15 17 10"></polyline>
            <line x1="12" y1="15" x2="12" y2="3"></line>
          </svg>
          <span>.PNG</span>
        </button>

        <button type="button" class="scanext-snip-btn-close" id="scanext-snip-close" title="Lukk (Esc)">✕</button>
      `;

      overlay.appendChild(currentToolbar);

      const PEN_COLORS = [
        { id: '#ef4444', title: 'Rød', dotBg: '#ef4444' },
        { id: '#3b82f6', title: 'Blå', dotBg: '#3b82f6' },
        { id: '#10b981', title: 'Grønn', dotBg: '#10b981' },
        { id: '#0f172a', title: 'Svart', dotBg: '#0f172a' }
      ];

      const HIGHLIGHTER_COLORS = [
        { id: 'rgba(254, 240, 138, 0.70)', title: 'Gul', dotBg: '#fde047' },
        { id: 'rgba(187, 247, 208, 0.70)', title: 'Grønn', dotBg: '#4ade80' },
        { id: 'rgba(251, 207, 232, 0.70)', title: 'Rosa', dotBg: '#f472b6' },
        { id: 'rgba(186, 230, 253, 0.70)', title: 'Blå', dotBg: '#38bdf8' }
      ];

      function updateColorBar() {
        const colorBar = currentToolbar.querySelector('#scanext-snip-colors');
        if (!colorBar) return;

        if (activeEditorTool === 'blur') {
          colorBar.style.display = 'flex';
          colorBar.innerHTML = `
            <button type="button" class="scanext-snip-sub-btn ${activeBlurMode === 'box' ? 'active' : ''}" id="scanext-blur-mode-box" title="Dra en boks over området">🔲 Boks</button>
            <button type="button" class="scanext-snip-sub-btn ${activeBlurMode === 'brush' ? 'active' : ''}" id="scanext-blur-mode-brush" title="Tegn over med bred børste">🖌️ Børste</button>
            ${activeBlurMode === 'brush' ? `
              <span style="width:1px;height:14px;background:rgba(255,255,255,0.25);margin:0 2px;"></span>
              <button type="button" class="scanext-snip-sub-btn ${activeBlurBrushRadius === 14 ? 'active' : ''}" data-radius="14" title="Tynn børste (28px)">S</button>
              <button type="button" class="scanext-snip-sub-btn ${activeBlurBrushRadius === 26 ? 'active' : ''}" data-radius="26" title="Medium børste (52px)">M</button>
              <button type="button" class="scanext-snip-sub-btn ${activeBlurBrushRadius === 45 ? 'active' : ''}" data-radius="45" title="Ekstra bred børste (90px)">L</button>
            ` : ''}
          `;

          colorBar.querySelector('#scanext-blur-mode-box').addEventListener('click', (e) => {
            e.stopPropagation();
            activeBlurMode = 'box';
            updateColorBar();
          });
          colorBar.querySelector('#scanext-blur-mode-brush').addEventListener('click', (e) => {
            e.stopPropagation();
            activeBlurMode = 'brush';
            updateColorBar();
          });

          if (activeBlurMode === 'brush') {
            colorBar.querySelectorAll('[data-radius]').forEach(btn => {
              btn.addEventListener('click', (e) => {
                e.stopPropagation();
                activeBlurBrushRadius = parseInt(btn.getAttribute('data-radius'), 10);
                updateColorBar();
              });
            });
          }
        } else if (activeEditorTool === 'pen') {
          colorBar.style.display = 'flex';
          colorBar.innerHTML = PEN_COLORS.map(c => `
            <div class="scanext-color-dot ${c.id === activePenColor ? 'active' : ''}" 
                 style="background:${c.dotBg};${c.id === '#0f172a' ? 'border:1px solid rgba(255,255,255,0.4);' : ''}" 
                 data-color="${c.id}" 
                 title="${c.title}"></div>
          `).join('');

          colorBar.querySelectorAll('.scanext-color-dot').forEach(dot => {
            dot.addEventListener('click', (e) => {
              e.stopPropagation();
              activePenColor = dot.getAttribute('data-color');
              updateColorBar();
            });
          });
        } else if (activeEditorTool === 'highlighter') {
          colorBar.style.display = 'flex';
          colorBar.innerHTML = HIGHLIGHTER_COLORS.map(c => `
            <div class="scanext-color-dot ${c.id === activeHighlighterColor ? 'active' : ''}" 
                 style="background:${c.dotBg};" 
                 data-color="${c.id}" 
                 title="${c.title}"></div>
          `).join('');

          colorBar.querySelectorAll('.scanext-color-dot').forEach(dot => {
            dot.addEventListener('click', (e) => {
              e.stopPropagation();
              activeHighlighterColor = dot.getAttribute('data-color');
              updateColorBar();
            });
          });
        } else {
          colorBar.style.display = 'none';
          colorBar.innerHTML = '';
        }
      }

      function setTool(toolName) {
        activeEditorTool = (activeEditorTool === toolName) ? 'none' : toolName;
        currentToolbar.querySelectorAll('.scanext-snip-btn').forEach(b => {
          if (b.id.startsWith('scanext-snip-tool-')) {
            b.classList.remove('scanext-snip-btn-active');
          }
        });
        if (activeEditorTool !== 'none') {
          const activeBtn = currentToolbar.querySelector(`#scanext-snip-tool-${activeEditorTool}`);
          if (activeBtn) activeBtn.classList.add('scanext-snip-btn-active');
          snipCanvas.style.cursor = 'crosshair';
        } else {
          snipCanvas.style.cursor = 'default';
        }
        updateColorBar();
      }

      currentToolbar.querySelector('#scanext-snip-tool-blur').addEventListener('click', (e) => {
        e.stopPropagation();
        setTool('blur');
      });

      currentToolbar.querySelector('#scanext-snip-tool-pen').addEventListener('click', (e) => {
        e.stopPropagation();
        setTool('pen');
      });

      currentToolbar.querySelector('#scanext-snip-tool-highlighter').addEventListener('click', (e) => {
        e.stopPropagation();
        setTool('highlighter');
      });

      currentToolbar.querySelector('#scanext-snip-undo').addEventListener('click', (e) => {
        e.stopPropagation();
        if (undoStack.length > 0) {
          const prev = undoStack.pop();
          snipCtx.putImageData(prev, 0, 0);
          updateUndoButton();
        }
      });

      function getCanvasBlob() {
        return new Promise(resolve => {
          snipCanvas.toBlob(blob => resolve(blob), 'image/png');
        });
      }

      // 1. Copy Image Handler
      currentToolbar.querySelector('#scanext-snip-copy-img').addEventListener('click', async (evt) => {
        evt.stopPropagation();
        evt.preventDefault();
        const btn = evt.currentTarget;
        btn.disabled = true;
        btn.textContent = 'Kopierer...';

        try {
          const blob = await getCanvasBlob();
          if (blob) {
            await navigator.clipboard.write([
              new ClipboardItem({ 'image/png': blob })
            ]);
            window.close();
          } else {
            alert('Kunne ikke generere utklipp.');
          }
        } catch (err) {
          console.error('Kopier bilde feilet:', err);
          alert('Feil ved kopiering: ' + err.message);
          window.close();
        }
      });

      // 2. Download PNG Handler
      currentToolbar.querySelector('#scanext-snip-download').addEventListener('click', async (evt) => {
        evt.stopPropagation();
        try {
          const blob = await getCanvasBlob();
          if (blob) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            const dateStr = new Date().toISOString().slice(0, 19).replace(/[:T]/g, '-');
            a.download = `ScanExtension_Utsnitt_${dateStr}.png`;
            document.body.appendChild(a);
            a.click();
            setTimeout(() => {
              a.remove();
              URL.revokeObjectURL(url);
              window.close();
            }, 600);
          }
        } catch (err) {
          console.error('Lagre bilde feilet:', err);
          window.close();
        }
      });

      // 3. Close Handler
      currentToolbar.querySelector('#scanext-snip-close').addEventListener('click', (evt) => {
        evt.stopPropagation();
        window.close();
      });
    });
  }
});
