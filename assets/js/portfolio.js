document.addEventListener("DOMContentLoaded", () => {
  const container = document.getElementById("portfolio-list");
  if (!container) return;

  fetch("assets/data/projects.json")
    .then((r) => r.json())
    .then((data) => {
      const projects = data.projects || [];
      container.innerHTML = projects.map((p) => renderProject(p)).join("\n");
    })
    .catch((err) => {
      container.innerHTML =
        '<p class="text-slate-400">خطأ بتحميل المشاريع.</p>';
      console.error(err);
    });

  function renderProject(p) {
    return `
      <div class="project-card-container group relative grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
        <div class="lg:col-span-8 relative">
          <div class="project-glow-bg absolute -inset-4 bg-sky-500/20 blur-2xl rounded-[40px]"></div>
          <div class="relative rounded-[32px] overflow-hidden aspect-[16/10] border border-white/10 shadow-2xl">
            <img alt="${escapeHtml(p.title)}" class="project-card-image w-full h-full object-cover" src="${escapeHtml(p.image)}" />
            <div class="absolute inset-0 bg-black/20 group-hover:bg-transparent transition-colors duration-500"></div>
          </div>
        </div>
        <div class="lg:col-span-4 text-right relative lg:-mr-12 z-20">
          <div class="bg-black/80 backdrop-blur-xl border border-white/10 p-8 rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] group-hover:border-sky-500/30 transition-colors duration-500">
            <span class="text-sky-400 font-mono text-sm tracking-widest mb-4 block">${escapeHtml(p.category)}</span>
            <h3 class="text-4xl font-bold text-white mb-4">${escapeHtml(p.title)}</h3>
            <p class="text-slate-400 leading-relaxed mb-8">${escapeHtml(p.description)}</p>
            <div class="flex gap-3 flex-wrap mb-8">
              ${(p.tags || []).map((t) => `<span class="px-3 py-1 bg-white/5 rounded-full text-xs text-slate-300 border border-white/5">${escapeHtml(t)}</span>`).join("")}
            </div>
            <button class="magnetic-btn flex items-center gap-3 text-white font-bold">عرض المشروع <span class="material-symbols-outlined text-sky-400">arrow_back</span></button>
          </div>
        </div>
      </div>
    `;
  }

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;");
  }
});
