export interface RenderOptions {
    canvasWidth: number;
    canvasHeight: number;
    palette: Record<number, string>;
}

export class CanvasRenderer {
    private ctx: CanvasRenderingContext2D;
    private options: RenderOptions;
    private imageData: ImageData | null = null;
    private paletteRgb: Map<number, [number, number, number]> = new Map();

    constructor(canvas: HTMLCanvasElement, options: RenderOptions) {
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) throw new Error('Failed to get canvas context');

        this.ctx = ctx;
        this.options = options;

        // Pre-compute RGB values for palette
        for (const [index, hex] of Object.entries(options.palette)) {
            const rgb = this.hexToRgb(hex);
            this.paletteRgb.set(parseInt(index), rgb);
        }
    }

    private hexToRgb(hex: string): [number, number, number] {
        const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
        if (!result) return [255, 255, 255];
        return [
            parseInt(result[1], 16),
            parseInt(result[2], 16),
            parseInt(result[3], 16),
        ];
    }

    renderFull(data: Uint8Array): void {
        const { canvasWidth, canvasHeight } = this.options;

        // Create image data if needed
        if (!this.imageData || this.imageData.width !== canvasWidth) {
            this.imageData = this.ctx.createImageData(canvasWidth, canvasHeight);
        }

        const pixels = this.imageData.data;

        for (let y = 0; y < canvasHeight; y++) {
            for (let x = 0; x < canvasWidth; x++) {
                const index = y * canvasWidth + x;
                const byteIndex = Math.floor(index / 2);
                const nibbleIndex = index % 2;

                let colorIndex = 15; // Default white
                if (byteIndex < data.length) {
                    const byte = data[byteIndex];
                    colorIndex = nibbleIndex === 0 ? (byte >> 4) & 0x0f : byte & 0x0f;
                }

                const rgb = this.paletteRgb.get(colorIndex) || [255, 255, 255];
                const pixelOffset = index * 4;

                pixels[pixelOffset] = rgb[0];
                pixels[pixelOffset + 1] = rgb[1];
                pixels[pixelOffset + 2] = rgb[2];
                pixels[pixelOffset + 3] = 255;
            }
        }

        this.ctx.putImageData(this.imageData, 0, 0);
    }

    renderPixel(x: number, y: number, colorIndex: number): void {
        const rgb = this.paletteRgb.get(colorIndex) || [255, 255, 255];

        if (this.imageData) {
            const index = y * this.options.canvasWidth + x;
            const pixelOffset = index * 4;

            this.imageData.data[pixelOffset] = rgb[0];
            this.imageData.data[pixelOffset + 1] = rgb[1];
            this.imageData.data[pixelOffset + 2] = rgb[2];
            this.imageData.data[pixelOffset + 3] = 255;

            // Update just this pixel
            this.ctx.putImageData(this.imageData, 0, 0, x, y, 1, 1);
        } else {
            // Fallback to fillRect
            this.ctx.fillStyle = this.options.palette[colorIndex] || '#FFFFFF';
            this.ctx.fillRect(x, y, 1, 1);
        }
    }

    getCanvas(): HTMLCanvasElement {
        return this.ctx.canvas;
    }

    clear(): void {
        this.ctx.fillStyle = '#FFFFFF';
        this.ctx.fillRect(0, 0, this.options.canvasWidth, this.options.canvasHeight);
    }
}
