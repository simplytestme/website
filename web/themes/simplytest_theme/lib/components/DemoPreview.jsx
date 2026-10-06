// The picture of a demo on its home page tile and its landing page.

// Agent Access is Drupal CMS, so a screenshot of it would repeat the Drupal
// CMS tile. What sets it apart happens in the agent, so it shows the
// connection details instead.
const AGENT_ACCESS = 'oneclickdemo_agent_access';

function AgentAccessPreview({ className, large }) {
  return (
    <div
      className={`${className} flex flex-col justify-center gap-1 bg-st-ink font-mono text-st-faint ${
        large
          ? 'px-10 text-base leading-[1.8]'
          : 'px-4 text-[11px] leading-[1.6]'
      }`}
    >
      <span className="flex items-center gap-2 text-st-accent-bright">
        <span
          className={`${large ? 'h-2.5 w-2.5' : 'h-1.5 w-1.5'} rounded-full bg-st-success`}
        />
        MCP server
      </span>
      <span className="truncate text-white">https://&lt;sandbox&gt;/mcp</span>
      <span>sign in with OAuth as admin</span>
    </div>
  );
}

/**
 * Renders the demo's screenshot, or `fallback` when it has none.
 *
 * @param {object} props
 * @param {{id: string, title: string, screenshot: string|null}} props.demo
 * @param {string} props.className
 *   Sizing and borders, shared by every variant.
 * @param {boolean} props.large
 *   Whether this is the landing page rather than a tile.
 * @param {JSX.Element} props.fallback
 */
function DemoPreview({ demo, className, large = false, fallback }) {
  if (demo.id === AGENT_ACCESS) {
    return <AgentAccessPreview className={className} large={large} />;
  }
  if (!demo.screenshot) {
    return fallback;
  }
  // Cropped from the top: the screenshots are taken so the part that
  // identifies the demo is there.
  return (
    <img
      src={demo.screenshot}
      alt={large ? `Screenshot of the ${demo.title} demo` : ''}
      loading="lazy"
      className={`${className} block w-full object-cover object-top`}
    />
  );
}

export default DemoPreview;
