export const API_BASE = import.meta.env.VITE_API_BASE_URL ?? '/';

async function j<T>(r: Response): Promise<T> { 
  if (!r.ok) throw new Error(`${r.status}`); 
  return r.json(); 
}

export const api = {
  get: async <T>(path: string) =>
    j<T>(await fetch(new URL(path, API_BASE), { 
      credentials: 'include', 
      cache: 'no-store', 
      headers: { 
        'Cache-Control': 'no-store' 
      } 
    })),
};