export default function ShowcaseNotice() {
  return (
    <aside
      className="fixed bottom-4 right-4 z-[100] max-w-[calc(100vw-2rem)] rounded-full border border-[#E5B869]/40 bg-[#111a20]/95 px-4 py-2 text-[11px] font-semibold text-[#f3d69b] shadow-xl backdrop-blur"
      role="status"
      aria-label="Showcase mode: sample content, no real transactions"
    >
      SHOWCASE · SAMPLE CONTENT · NO REAL PAYMENTS
    </aside>
  );
}
