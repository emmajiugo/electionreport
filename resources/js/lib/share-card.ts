export type ShareCardDetails = {
    lga: string;
    state: string;
    election: string;
};

// Cards are shared to the public, so they always point at the public site, even from staging.
export const publicSite = 'electionreport.ng';

const size = 1080;
const font = '"Instrument Sans", ui-sans-serif, system-ui, sans-serif';

function wrap(
    context: CanvasRenderingContext2D,
    text: string,
    maxWidth: number,
): string[] {
    const lines: string[] = [];
    let line = '';

    for (const word of text.split(' ')) {
        const candidate = line ? `${line} ${word}` : word;

        if (line && context.measureText(candidate).width > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = candidate;
        }
    }

    return [...lines, line];
}

/**
 * Draws the "I reported" card as a square PNG for WhatsApp, X and Instagram. It only knows where
 * and in which election the voter reported, never how they voted.
 */
export async function drawShareCard(
    details: ShareCardDetails,
    { practice }: { practice: boolean },
): Promise<Blob> {
    await Promise.all([
        document.fonts.load(`600 80px ${font}`),
        document.fonts.load(`400 40px ${font}`),
    ]);

    const canvas = document.createElement('canvas');
    canvas.width = size;
    canvas.height = size;
    const context = canvas.getContext('2d');

    if (!context) {
        throw new Error('Canvas is not supported.');
    }

    const background = context.createLinearGradient(0, 0, size, size);
    background.addColorStop(0, '#14532d');
    background.addColorStop(1, '#15803d');
    context.fillStyle = background;
    context.fillRect(0, 0, size, size);

    context.textBaseline = 'alphabetic';
    context.fillStyle = '#ffffff';
    context.font = `600 44px ${font}`;
    context.fillText('ElectionReport', 80, 120);

    if (practice) {
        const label = 'PRACTICE';
        context.font = `600 28px ${font}`;
        const width = context.measureText(label).width + 36;
        context.fillStyle = '#facc15';
        context.beginPath();
        context.roundRect(size - 80 - width, 84, width, 48, 24);
        context.fill();
        context.fillStyle = '#422006';
        context.fillText(label, size - 80 - width + 18, 118);
    }

    // A ballot paper dropping into a transparent ballot box.
    context.fillStyle = 'rgba(255, 255, 255, 0.95)';
    context.beginPath();
    context.roundRect(450, 150, 180, 105, 10);
    context.fill();
    context.fillStyle = '#9ca3af';
    for (const y of [175, 198, 221]) {
        context.fillRect(478, y, 124, 8);
    }
    context.fillStyle = 'rgba(255, 255, 255, 0.25)';
    context.strokeStyle = 'rgba(255, 255, 255, 0.9)';
    context.lineWidth = 6;
    context.beginPath();
    context.roundRect(380, 230, 320, 190, 18);
    context.fill();
    context.stroke();
    context.fillStyle = '#14532d';
    context.fillRect(460, 224, 160, 14);

    context.fillStyle = '#ffffff';
    context.textAlign = 'center';
    context.font = `600 80px ${font}`;
    let y = 550;
    for (const line of wrap(context, 'I reported from my polling unit', 900)) {
        context.fillText(line, size / 2, y);
        y += 92;
    }

    context.fillStyle = '#fde68a';
    context.font = `600 56px ${font}`;
    for (const line of wrap(context, `${details.lga}, ${details.state}`, 900)) {
        context.fillText(line, size / 2, y + 10);
        y += 66;
    }

    context.fillStyle = 'rgba(255, 255, 255, 0.85)';
    context.font = `400 38px ${font}`;
    context.fillText(details.election, size / 2, y + 40);

    context.textAlign = 'left';
    context.fillStyle = '#ffffff';
    context.font = `600 36px ${font}`;
    context.fillText(`Report yours at ${publicSite}`, 80, size - 100);
    context.fillStyle = 'rgba(255, 255, 255, 0.75)';
    context.font = `400 28px ${font}`;
    context.fillText(
        practice
            ? 'Practice report. It does not count. Not affiliated with INEC.'
            : 'Voter-reported, not official results. Not affiliated with INEC.',
        80,
        size - 56,
    );

    return new Promise((resolve, reject) =>
        canvas.toBlob(
            (blob) =>
                blob
                    ? resolve(blob)
                    : reject(new Error('Could not draw card.')),
            'image/png',
        ),
    );
}
