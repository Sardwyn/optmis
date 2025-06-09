export async function runParsers(parsers, doc) {
    for (const fn of parsers) {
      try {
        const result = await fn(doc);
        if (result?.length) return result;
      } catch (err) {
        console.warn(`Parser failed: ${fn.name}`, err);
      }
    }
    return [];
  }
  