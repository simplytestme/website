// The three-diamond mark from Tugboat's logo, inlined so the progress page
// does not need a theme asset path passed through drupalSettings.
function TugboatMark({ className }) {
  return (
    <svg
      viewBox="0 0 26 32"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden="true"
    >
      <path
        d="M25.3276 18.9928L13.2669 7.02207C13.0449 6.8017 12.6849 6.8017 12.4629 7.02207L0.402185 18.9928C0.180154 19.2132 0.180154 19.5705 0.402185 19.7909L12.4629 31.7617C12.6849 31.9821 13.0449 31.9821 13.2669 31.7617L25.3276 19.7909C25.5496 19.5705 25.5496 19.2132 25.3276 18.9928Z"
        fill="#8EB3C1"
      />
      <path
        d="M25.3274 12.6088L13.2667 0.638039C13.0447 0.417664 12.6847 0.417664 12.4627 0.638039L0.401966 12.6088C0.179934 12.8292 0.179935 13.1865 0.401966 13.4069L12.4627 25.3776C12.6847 25.598 13.0447 25.598 13.2667 25.3776L25.3274 13.4069C25.5494 13.1865 25.5494 12.8292 25.3274 12.6088Z"
        fill="#D4E5EB"
      />
      <path
        d="M22.1111 3.82991L13.2671 12.608C13.1601 12.7135 13.0154 12.7727 12.8647 12.7727C12.7139 12.7727 12.5692 12.7135 12.4623 12.608L10.0504 10.2129C9.9436 10.1075 9.79916 10.0483 9.64859 10.0483C9.49803 10.0483 9.35358 10.1075 9.24682 10.2129L6.83492 12.608C6.78205 12.6603 6.7401 12.7225 6.71148 12.7909C6.68286 12.8593 6.66812 12.9327 6.66812 13.0068C6.66812 13.0809 6.68286 13.1543 6.71148 13.2227C6.7401 13.2911 6.78205 13.3533 6.83492 13.4056L9.64859 16.1983L12.4623 18.9922C12.5151 19.0447 12.5778 19.0864 12.6468 19.1148C12.7159 19.1433 12.7899 19.1579 12.8647 19.1579C12.9394 19.1579 13.0134 19.1433 13.0825 19.1148C13.1515 19.0864 13.2143 19.0447 13.2671 18.9922L25.3278 7.02138C25.3807 6.96897 25.4227 6.90671 25.4514 6.83818C25.48 6.76964 25.4947 6.69617 25.4947 6.62198C25.4947 6.54778 25.48 6.47431 25.4514 6.40578C25.4227 6.33724 25.3807 6.27499 25.3278 6.22257L22.9159 3.82991C22.809 3.72446 22.6643 3.66528 22.5135 3.66528C22.3628 3.66528 22.2181 3.72446 22.1111 3.82991Z"
        fill="#31758E"
      />
    </svg>
  );
}

// Shown while a build is running. The wait is the one moment someone is
// looking at the page with nothing to do, so it is where the platform credit
// gets read.
function TugboatCallout() {
  return (
    <a
      href="https://www.tugboatqa.com/"
      className="group relative flex items-start gap-5 overflow-hidden rounded-[14px] border border-st-tugboat-line bg-st-tugboat-tint p-6 text-st-body hover:border-st-tugboat"
    >
      <TugboatMark className="pointer-events-none absolute -bottom-10 -right-6 h-[180px] w-auto opacity-[0.12]" />
      <span className="flex h-12 w-12 flex-none items-center justify-center rounded-xl border border-st-tugboat-line bg-white">
        <TugboatMark className="h-7 w-auto" />
      </span>
      <span className="relative flex flex-col gap-1.5">
        <span className="font-mono text-[11px] uppercase tracking-[0.12em] text-st-tugboat">
          Built on Tugboat
        </span>
        <span className="text-[15px] font-semibold leading-snug text-st-ink">
          Your sandbox is a real Tugboat preview
        </span>
        <span className="text-sm leading-relaxed text-st-slate">
          Tugboat builds a fresh set of containers for every launch, the same
          way it builds a preview for every pull request on a real project. That
          is why a full Drupal site comes up in about a minute and cleans itself
          up two hours later.
        </span>
        <span className="mt-1 text-[13px] font-semibold text-st-tugboat group-hover:underline">
          See what Tugboat does &rarr;
        </span>
      </span>
    </a>
  );
}

export default TugboatCallout;
