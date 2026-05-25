const registry = new Map<string, string>();

export function registerApp(appName: string, apiKey: string): void {
  registry.set(appName, apiKey);
}

export function getAppKey(appName: string): string | undefined {
  return registry.get(appName);
}

export function deregisterApp(appName: string): void {
  registry.delete(appName);
}

export function listApps(): Array<{ appName: string }> {
  return Array.from(registry.keys()).map((appName) => ({ appName }));
}
