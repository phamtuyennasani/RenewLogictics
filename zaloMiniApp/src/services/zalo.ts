const memory = new Map<string, string>();

export type ZaloUserInfo = {
  id?: string;
  idByOA?: string;
  name?: string;
  avatar?: string;
  followedOA?: boolean;
  isSensitive?: boolean;
};

export type ZaloLoginContext = {
  accessToken: string;
  userInfo?: ZaloUserInfo;
};

function browserStorage(): Storage | null {
  try {
    return window.localStorage;
  } catch {
    return null;
  }
}

export function getStoredItem(key: string): string | null {
  const storage = browserStorage();
  if (!storage) {
    return memory.get(key) ?? null;
  }

  return storage.getItem(key);
}

export function setStoredItem(key: string, value: string): void {
  const storage = browserStorage();
  if (!storage) {
    memory.set(key, value);
    return;
  }

  storage.setItem(key, value);
}

export function removeStoredItem(key: string): void {
  const storage = browserStorage();
  if (!storage) {
    memory.delete(key);
    return;
  }

  storage.removeItem(key);
}

export async function getZaloAccessToken(): Promise<string | undefined> {
  try {
    const { getAccessToken } = await import("zmp-sdk/apis");
    const token = await getAccessToken();
    return token || undefined;
  } catch {
    return undefined;
  }
}

export async function getZaloLoginContext(options: { requestUserInfo?: boolean } = {}): Promise<ZaloLoginContext> {
  try {
    const zmpApis = await import("zmp-sdk/apis");
    const accessToken = await zmpApis.getAccessToken();

    if (!accessToken) {
      throw new Error("empty_access_token");
    }

    let userInfo: ZaloUserInfo | undefined;

    if (options.requestUserInfo ?? true) {
      try {
        await zmpApis.authorize({ scopes: ["scope.userInfo"] });
        const result = await zmpApis.getUserInfo({
          autoRequestPermission: true,
          avatarType: "normal",
        });
        userInfo = result.userInfo as ZaloUserInfo;
      } catch {
        // Access token is enough for backend identity verification. User info is optional.
      }
    }

    return { accessToken, userInfo };
  } catch {
    throw new Error("Không lấy được quyền Zalo. Vui lòng mở ứng dụng trong Zalo Mini App hoặc đăng nhập bằng tài khoản hệ thống.");
  }
}
