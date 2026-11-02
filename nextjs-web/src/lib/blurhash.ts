/**
 * Lightweight, zero-dependency Wolt BlurHash decoder and dataURL generator.
 * Converts BlurHash strings to fast, smooth image placeholders.
 */

const digitCharacters =
  '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz#$%*+,-.:;=?@[]^_{|}~';

const decode83 = (str: string): number => {
  let value = 0;
  for (let i = 0; i < str.length; i++) {
    const c = str[i];
    const digit = digitCharacters.indexOf(c);
    if (digit !== -1) {
      value = value * 83 + digit;
    }
  }
  return value;
};

const sRGBToLinear = (value: number): number => {
  const v = value / 255;
  if (v <= 0.04045) {
    return v / 12.92;
  }
  return Math.pow((v + 0.055) / 1.055, 2.4);
};

const linearTosRGB = (value: number): number => {
  const v = Math.max(0, Math.min(1, value));
  if (v <= 0.0031308) {
    return Math.round(v * 12.92 * 255);
  }
  return Math.round((1.055 * Math.pow(v, 1 / 2.4) - 0.055) * 255);
};

const signPow = (val: number, exp: number): number => {
  return Math.sign(val) * Math.pow(Math.abs(val), exp);
};

/**
 * Decodes a BlurHash string into an RGBA pixel array
 */
export function decodeBlurHash(
  blurhash: string,
  width: number,
  height: number,
  punch = 1
): Uint8ClampedArray | null {
  if (!blurhash || blurhash.length < 6) {
    return null;
  }

  const sizeFlag = decode83(blurhash[0]);
  const numY = Math.floor(sizeFlag / 9) + 1;
  const numX = (sizeFlag % 9) + 1;

  const quantisedMaxValue = decode83(blurhash[1]);
  const maxValue = ((quantisedMaxValue + 1) / 166) * punch;

  const colors = new Array<[number, number, number]>(numX * numY);

  const averageColor = decode83(blurhash.substring(2, 6));
  colors[0] = [
    sRGBToLinear(averageColor >> 16),
    sRGBToLinear((averageColor >> 8) & 255),
    sRGBToLinear(averageColor & 255),
  ];

  for (let i = 1; i < numX * numY; i++) {
    const value = decode83(blurhash.substring(4 + i * 2, 6 + i * 2));
    colors[i] = [
      signPow((Math.floor(value / (19 * 19)) - 9) / 9, 2.0) * maxValue,
      signPow(((Math.floor(value / 19) % 19) - 9) / 9, 2.0) * maxValue,
      signPow(((value % 19) - 9) / 9, 2.0) * maxValue,
    ];
  }

  const bytes = new Uint8ClampedArray(width * height * 4);

  for (let y = 0; y < height; y++) {
    for (let x = 0; x < width; x++) {
      let r = 0;
      let g = 0;
      let b = 0;

      for (let j = 0; j < numY; j++) {
        for (let i = 0; i < numX; i++) {
          const basis =
            Math.cos((Math.PI * x * i) / width) * Math.cos((Math.PI * y * j) / height);
          const color = colors[i + j * numX];
          r += color[0] * basis;
          g += color[1] * basis;
          b += color[2] * basis;
        }
      }

      const intR = linearTosRGB(r);
      const intG = linearTosRGB(g);
      const intB = linearTosRGB(b);

      const offset = 4 * (x + y * width);
      bytes[offset] = intR;
      bytes[offset + 1] = intG;
      bytes[offset + 2] = intB;
      bytes[offset + 3] = 255;
    }
  }

  return bytes;
}

// Global cache for decoded BlurHash data URLs
const dataUrlCache = new Map<string, string>();

/**
 * Converts BlurHash into an ultra-fast base64 dataURL for instant inline display
 */
export function blurHashToDataURL(blurhash?: string | null, width = 32, height = 32): string | null {
  if (!blurhash || typeof window === 'undefined') {
    return null;
  }

  const cacheKey = `${blurhash}_${width}_${height}`;
  if (dataUrlCache.has(cacheKey)) {
    return dataUrlCache.get(cacheKey)!;
  }

  try {
    const pixels = decodeBlurHash(blurhash, width, height);
    if (!pixels) return null;

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) return null;

    const imgData = ctx.createImageData(width, height);
    imgData.data.set(pixels);
    ctx.putImageData(imgData, 0, 0);

    const dataUrl = canvas.toDataURL('image/png');
    dataUrlCache.set(cacheKey, dataUrl);
    return dataUrl;
  } catch {
    return null;
  }
}
