/**
 * Parses text with embedded chords in [Chord] format.
 * Returns an array of lines, where each line contains an array of segments.
 * Each segment has:
 * - chord: string | null
 * - lyric: string
 */
export function parseChords(text) {
    if (!text) return [];

    const lines = text.split('\n');
    const parsedLines = [];

    lines.forEach(line => {
        const segments = [];
        let currentChord = null;

        // Split by chord pattern, keeping delimiters
        // Example: "Yesus [G]kau sungguh [D]baik"
        // Tokens: "Yesus ", "[G]", "kau sungguh ", "[D]", "baik"
        // Note: split with capturing group returns the separator too.
        const tokens = line.split(/(\[.*?\])/g);

        tokens.forEach(token => {
            if (token.startsWith('[') && token.endsWith(']')) {
                // It's a chord
                const chord = token.slice(1, -1);

                // If we already have a pending chord, it means we had [C][D]...
                // Push a segment for the previous chord with empty lyric
                if (currentChord !== null) {
                    segments.push({ chord: currentChord, lyric: "" });
                }

                currentChord = chord;
            } else {
                // It's lyrics or empty string
                if (!token) return;

                // Push segment with current chord (if any) and this lyric
                segments.push({ chord: currentChord, lyric: token });
                currentChord = null; // Reset chord after using it
            }
        });

        // If there is a trailing chord at the end of the line "[End]"
        if (currentChord !== null) {
            segments.push({ chord: currentChord, lyric: "" });
        }

        // If line was empty or just whitespace, ensure we push something so empty lines render?
        // Actually, if segments is empty, we push an empty segment to maintain line height?
        if (segments.length === 0 && line.trim() === '') {
            segments.push({ chord: null, lyric: "" }); // Empty line placeholder
        } else if (segments.length === 0) {
            // Line had content but regex weirdness? Just push line as lyric
            segments.push({ chord: null, lyric: line });
        }

        parsedLines.push(segments);
    });

    return parsedLines;
}
