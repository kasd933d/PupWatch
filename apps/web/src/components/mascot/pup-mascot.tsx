import type { Mood } from "@/lib/mood";
import { MOOD_LABEL } from "@/lib/mood";

/**
 * Original pixel-art pup drawn with SVG rectangles (PRD §3.1).
 *
 * Every mood changes ears, eyes and mouth; the CSS animations live in
 * globals.css and are disabled under prefers-reduced-motion.
 */
export function PupMascot({
  mood,
  size = 96,
  className = "",
}: {
  mood: Mood;
  size?: number;
  className?: string;
}) {
  const tilt = mood === "curious" ? "rotate(-8 32 36)" : undefined;

  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 64 64"
      role="img"
      aria-label={`PupWatch mascot: ${MOOD_LABEL[mood].toLowerCase()}`}
      className={`shrink-0 ${className}`}
      shapeRendering="crispEdges"
    >
      <g transform={tilt}>
        {/* Rain cloud for the sad mood */}
        {mood === "sad" && (
          <g className="pup-rain" aria-hidden="true">
            <rect x="14" y="2" width="16" height="4" fill="#94a3b8" />
            <rect x="10" y="6" width="24" height="4" fill="#94a3b8" />
            <rect x="16" y="10" width="3" height="3" fill="#60a5fa" />
            <rect x="24" y="10" width="3" height="3" fill="#60a5fa" />
          </g>
        )}

        {/* Ears */}
        {mood === "sad" ? (
          <>
            <rect x="8" y="20" width="6" height="10" fill="#8a5a33" />
            <rect x="50" y="20" width="6" height="10" fill="#8a5a33" />
          </>
        ) : (
          <>
            <rect x="6" y="12" width="8" height="14" fill="#8a5a33" />
            <rect x="50" y="12" width="8" height="14" fill="#8a5a33" />
            <rect x="8" y="14" width="4" height="8" fill="#d9a06b" />
            <rect x="52" y="14" width="4" height="8" fill="#d9a06b" />
          </>
        )}

        {/* Head */}
        <rect x="12" y="18" width="40" height="30" fill="#b5763f" />
        <rect x="14" y="16" width="36" height="4" fill="#b5763f" />

        {/* Muzzle */}
        <rect x="20" y="36" width="24" height="12" fill="#e8c39e" />

        {/* Eyes */}
        {mood === "sleeping" ? (
          <>
            <rect x="20" y="28" width="6" height="2" fill="#2b1b10" />
            <rect x="38" y="28" width="6" height="2" fill="#2b1b10" />
          </>
        ) : mood === "sad" ? (
          <>
            <rect x="20" y="27" width="5" height="4" fill="#2b1b10" />
            <rect x="39" y="27" width="5" height="4" fill="#2b1b10" />
            <rect x="19" y="26" width="6" height="1" fill="#2b1b10" />
            <rect x="40" y="26" width="6" height="1" fill="#2b1b10" />
          </>
        ) : mood === "curious" ? (
          <>
            <rect x="21" y="26" width="5" height="6" fill="#2b1b10" />
            <rect x="38" y="26" width="5" height="6" fill="#2b1b10" />
            <rect x="22" y="27" width="2" height="2" fill="#ffffff" />
            <rect x="39" y="27" width="2" height="2" fill="#ffffff" />
          </>
        ) : (
          <>
            <rect x="20" y="26" width="5" height="5" fill="#2b1b10" />
            <rect x="39" y="26" width="5" height="5" fill="#2b1b10" />
          </>
        )}

        {/* Nose */}
        <rect x="29" y="38" width="6" height="4" fill="#2b1b10" />

        {/* Mouth / tongue */}
        {mood === "happy" && (
          <>
            <rect x="26" y="43" width="12" height="2" fill="#2b1b10" />
            <rect x="26" y="43" width="2" height="4" fill="#2b1b10" />
            <rect x="36" y="43" width="2" height="4" fill="#2b1b10" />
          </>
        )}
        {mood === "panting" && (
          <>
            <rect x="26" y="43" width="12" height="2" fill="#2b1b10" />
            <rect x="29" y="45" width="6" height="6" fill="#ef7a7a" />
            <rect x="42" y="18" width="3" height="5" fill="#7dd3fc" />
            <rect x="44" y="22" width="2" height="2" fill="#7dd3fc" />
          </>
        )}
        {mood === "sad" && <rect x="26" y="44" width="12" height="2" fill="#2b1b10" />}
        {mood === "sleeping" && <rect x="28" y="44" width="8" height="2" fill="#2b1b10" />}
        {mood === "curious" && <rect x="29" y="44" width="6" height="3" fill="#2b1b10" />}

        {/* Cheeks */}
        <rect x="16" y="36" width="4" height="3" fill="#d98f8f" />
        <rect x="44" y="36" width="4" height="3" fill="#d98f8f" />

        {/* Tail — wags when happy */}
        <g className={mood === "happy" ? "pup-wag" : undefined}>
          <rect x="52" y="42" width="6" height="4" fill="#8a5a33" />
          <rect x="56" y="36" width="4" height="8" fill="#8a5a33" />
        </g>
      </g>

      {/* Mood extras outside the tilt so they stay upright */}
      {mood === "sleeping" && (
        <g className="pup-float" aria-hidden="true">
          <text x="46" y="16" fontSize="12" fontWeight="700" fill="#8b8b8b">
            z
          </text>
          <text x="54" y="10" fontSize="8" fontWeight="700" fill="#a3a3a3">
            z
          </text>
        </g>
      )}

      {mood === "curious" && (
        <text x="48" y="14" fontSize="18" fontWeight="700" fill="#f59e0b" aria-hidden="true">
          ?
        </text>
      )}
    </svg>
  );
}
