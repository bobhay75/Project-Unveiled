(() => {
  "use strict";

  const body = document.body;
  const root = document.documentElement;
  const theme = body.dataset.cinematicTheme;
  if (!theme) return;

  const reduced = matchMedia("(prefers-reduced-motion: reduce)");
  const fine = matchMedia("(hover: hover) and (pointer: fine)");
  const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
  const lowPower = Boolean(connection && connection.saveData) || Number(navigator.deviceMemory || 8) <= 4;

  root.classList.add("cinematic-ready");

  const progress = document.createElement("div");
  progress.className = "cinematic-progress";
  progress.setAttribute("aria-hidden", "true");
  body.append(progress);

  const selectors = theme === "bobsome1"
    ? [".hero-copy > *", ".proof-strip > div", ".split-heading > *", ".section-heading > *",
       ".service-card", ".project-card", ".lab-band > *", ".venue-grid > *",
       ".reason-grid article", ".process-list li", ".contact-panel > *"]
    : [".workspace-heading > *", ".method-promise > div", "#claim-form > *",
       ".docket-panel > *", ".observer-desk > *", ".source-sweep > *", ".report-card",
       ".evidence-workbench > *", ".judgment-card > *", ".proof-desk > *", ".privacy-desk > *"];

  const revealTargets = Array.from(document.querySelectorAll(selectors.join(",")));
  revealTargets.forEach((element, index) => {
    element.classList.add("cinematic-reveal");
    element.style.setProperty("--reveal-delay", String(Math.min(index % 5, 4) * 55) + "ms");
  });

  if (!reduced.matches && "IntersectionObserver" in window) {
    const observer = new IntersectionObserver((entries, activeObserver) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add("is-visible");
        activeObserver.unobserve(entry.target);
      });
    }, { rootMargin: "0px 0px -8%", threshold: 0.08 });
    revealTargets.forEach((element) => {
      if (element.getBoundingClientRect().top < innerHeight * 0.92) element.classList.add("is-visible");
      else observer.observe(element);
    });
  } else {
    revealTargets.forEach((element) => element.classList.add("is-visible"));
  }

  let scrollQueued = false;
  const syncScroll = () => {
    scrollQueued = false;
    const max = Math.max(1, document.documentElement.scrollHeight - innerHeight);
    root.style.setProperty("--page-progress", Math.min(1, Math.max(0, scrollY / max)).toFixed(4));
    root.style.setProperty("--hero-scroll", String(Math.min(scrollY, 720) * 0.12) + "px");
  };
  addEventListener("scroll", () => {
    if (scrollQueued) return;
    scrollQueued = true;
    requestAnimationFrame(syncScroll);
  }, { passive: true });
  syncScroll();

  if (!reduced.matches && fine.matches) {
    addEventListener("pointermove", (event) => {
      const x = event.clientX / Math.max(1, innerWidth);
      const y = event.clientY / Math.max(1, innerHeight);
      root.style.setProperty("--pointer-x", String(event.clientX) + "px");
      root.style.setProperty("--pointer-y", String(event.clientY) + "px");
      root.style.setProperty("--hero-x", String((0.5 - x) * 12) + "px");
      root.style.setProperty("--hero-pointer-y", String((0.5 - y) * 8) + "px");
    }, { passive: true });
  }

  if (theme === "bobsome1" && !reduced.matches && fine.matches) {
    document.querySelectorAll(".project-card").forEach((card) => {
      card.addEventListener("pointermove", (event) => {
        const rect = card.getBoundingClientRect();
        const x = Math.min(1, Math.max(0, (event.clientX - rect.left) / rect.width));
        const y = Math.min(1, Math.max(0, (event.clientY - rect.top) / rect.height));
        card.style.setProperty("--card-x", String(x * 100) + "%");
        card.style.setProperty("--card-y", String(y * 100) + "%");
        card.style.setProperty("--tilt-x", String((0.5 - y) * 3.2) + "deg");
        card.style.setProperty("--tilt-y", String((x - 0.5) * 4.2) + "deg");
      }, { passive: true });
      card.addEventListener("pointerleave", () => {
        card.style.setProperty("--tilt-x", "0deg");
        card.style.setProperty("--tilt-y", "0deg");
      });
    });

    const steps = Array.from(document.querySelectorAll(".process-list li"));
    if (steps.length && "IntersectionObserver" in window) {
      const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
          if (!entry.isIntersecting) return;
          steps.forEach((step) => step.classList.toggle("is-current", step === entry.target));
        });
      }, { rootMargin: "-32% 0px -52%", threshold: 0 });
      steps.forEach((step) => observer.observe(step));
    }
  }

  if (theme === "trustworthy") {
    const stages = Array.from(document.querySelectorAll(".stage"));
    const syncStages = () => {
      const index = Math.max(0, stages.findIndex((stage) => stage.classList.contains("is-active")));
      root.style.setProperty("--stage-progress", String(stages.length > 1 ? index / (stages.length - 1) : 0));
    };
    syncStages();
    if (stages.length && "MutationObserver" in window) {
      const observer = new MutationObserver(syncStages);
      stages.forEach((stage) => observer.observe(stage, { attributes: true, attributeFilter: ["class"] }));
    }
    const claim = document.querySelector("#claim-input");
    if (claim) {
      const syncClaim = () => body.classList.toggle("claim-engaged", Boolean(claim.value.trim()));
      claim.addEventListener("input", syncClaim);
      claim.addEventListener("focus", () => body.classList.add("claim-focused"));
      claim.addEventListener("blur", () => body.classList.remove("claim-focused"));
      syncClaim();
    }
  }

  class SignalField {
    constructor(host, fieldTheme) {
      this.host = host;
      this.theme = fieldTheme;
      this.canvas = document.createElement("canvas");
      this.canvas.className = "cinematic-canvas";
      this.canvas.setAttribute("aria-hidden", "true");
      this.context = this.canvas.getContext("2d", { alpha: true });
      if (!this.context) return;
      host.prepend(this.canvas);
      this.visible = true;
      this.lastFrame = 0;
      this.pointer = { x: 0.5, y: 0.42 };
      this.resizeObserver = new ResizeObserver(() => this.resize());
      this.resizeObserver.observe(host);
      this.visibilityObserver = new IntersectionObserver((entries) => {
        this.visible = entries.some((entry) => entry.isIntersecting);
      }, { rootMargin: "120px" });
      this.visibilityObserver.observe(host);
      if (!reduced.matches && fine.matches) {
        host.addEventListener("pointermove", (event) => {
          const rect = host.getBoundingClientRect();
          this.pointer.x = (event.clientX - rect.left) / Math.max(1, rect.width);
          this.pointer.y = (event.clientY - rect.top) / Math.max(1, rect.height);
        }, { passive: true });
      }
      this.resize();
      if (reduced.matches || (connection && connection.saveData)) this.draw(0, true);
      else requestAnimationFrame((time) => this.animate(time));
    }

    resize() {
      const rect = this.host.getBoundingClientRect();
      const density = Math.min(devicePixelRatio || 1, lowPower ? 1.25 : 1.75);
      this.width = Math.max(1, Math.round(rect.width));
      this.height = Math.max(1, Math.round(rect.height));
      this.canvas.width = Math.round(this.width * density);
      this.canvas.height = Math.round(this.height * density);
      this.canvas.style.width = String(this.width) + "px";
      this.canvas.style.height = String(this.height) + "px";
      this.context.setTransform(density, 0, 0, density, 0, 0);
      const count = lowPower ? 14 : (this.theme === "trustworthy" ? 28 : 22);
      this.nodes = Array.from({ length: count }, (_, index) => ({
        x: Math.random(), y: Math.random(),
        vx: (Math.random() - 0.5) * 0.00005,
        vy: (Math.random() - 0.5) * 0.00005,
        size: index % 7 === 0 ? 2.4 : 1.25,
        source: index % 5 === 0
      }));
      this.draw(0, true);
    }

    animate(time) {
      if (this.visible && time - this.lastFrame > (lowPower ? 62 : 36)) {
        const delta = Math.min(40, Math.max(0, time - this.lastFrame));
        this.lastFrame = time;
        this.draw(delta, false);
      }
      requestAnimationFrame((next) => this.animate(next));
    }

    draw(delta, still) {
      const ctx = this.context;
      if (!ctx || !this.width || !this.height) return;
      ctx.clearRect(0, 0, this.width, this.height);
      const palette = this.theme === "trustworthy"
        ? { line: "98,205,241", node: "232,184,95", source: "117,222,176" }
        : { line: "114,196,232", node: "255,178,63", source: "255,212,138" };
      const focalX = this.pointer.x * this.width;
      const focalY = this.pointer.y * this.height;
      const glow = ctx.createRadialGradient(focalX, focalY, 0, focalX, focalY, Math.max(this.width, this.height) * 0.48);
      glow.addColorStop(0, "rgba(" + palette.line + ",0.11)");
      glow.addColorStop(1, "rgba(" + palette.line + ",0)");
      ctx.fillStyle = glow;
      ctx.fillRect(0, 0, this.width, this.height);

      this.nodes.forEach((node) => {
        if (!still) {
          node.x += node.vx * delta; node.y += node.vy * delta;
          if (node.x < -0.03 || node.x > 1.03) node.vx *= -1;
          if (node.y < -0.03 || node.y > 1.03) node.vy *= -1;
        }
      });
      const maxDistance = Math.min(190, Math.max(105, this.width * 0.16));
      for (let a = 0; a < this.nodes.length; a += 1) {
        const first = this.nodes[a], firstX = first.x * this.width, firstY = first.y * this.height;
        for (let b = a + 1; b < this.nodes.length; b += 1) {
          const second = this.nodes[b], secondX = second.x * this.width, secondY = second.y * this.height;
          const distance = Math.hypot(firstX - secondX, firstY - secondY);
          if (distance > maxDistance) continue;
          ctx.beginPath(); ctx.moveTo(firstX, firstY); ctx.lineTo(secondX, secondY);
          ctx.strokeStyle = "rgba(" + palette.line + "," + ((1 - distance / maxDistance) * 0.17) + ")";
          ctx.lineWidth = 0.7; ctx.stroke();
        }
      }
      this.nodes.forEach((node) => {
        ctx.beginPath(); ctx.arc(node.x * this.width, node.y * this.height, node.size, 0, Math.PI * 2);
        ctx.fillStyle = "rgba(" + (node.source ? palette.source : palette.node) + "," + (node.source ? 0.68 : 0.43) + ")";
        ctx.fill();
      });
    }
  }

  const fieldHost = theme === "bobsome1" ? document.querySelector(".hero") : document.querySelector(".case-workspace");
  if (fieldHost && "ResizeObserver" in window && "IntersectionObserver" in window) new SignalField(fieldHost, theme);
})();