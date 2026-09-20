import path from 'path';
import { fileURLToPath } from 'url';

// Helper for ES Modules
export function getDirname(importMetaUrl: string) {
  return path.dirname(fileURLToPath(importMetaUrl));
}
