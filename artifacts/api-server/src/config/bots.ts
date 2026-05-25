export interface BotFieldOption {
  value: string;
  label: string;
}

export interface BotField {
  key: string;
  envVar: string;
  label: string;
  placeholder: string;
  required: boolean;
  type?: "text" | "password" | "select";
  options?: BotFieldOption[];
}

export interface BotConfig {
  name: string;
  repoOwner: string;
  repoName: string;
  branch: string;
  containerStack?: boolean;
  fields: BotField[];
}

const BOOL_OPTIONS: BotFieldOption[] = [
  { value: "true", label: "Yes (true)" },
  { value: "false", label: "No (false)" },
];

const MODE_OPTIONS: BotFieldOption[] = [
  { value: "public", label: "Public" },
  { value: "private", label: "Private" },
];

export const BOTS: Record<string, BotConfig> = {
  cypherx: {
    name: "CypherX",
    repoOwner: "TristanCage",
    repoName: "CypherX",
    branch: "main",
    containerStack: true,
    fields: [
      {
        key: "sessionId",
        envVar: "SESSION_ID",
        label: "Session ID",
        placeholder: "paste WhatsApp session string...",
        required: true,
      },
    ],
  },

  bwm: {
    name: "BWM-XMD",
    repoOwner: "Bwmxmd254",
    repoName: "BWM-XMD-GO",
    branch: "main",
    containerStack: false,
    fields: [
      {
        key: "session",
        envVar: "SESSION",
        label: "Session ID",
        placeholder: "paste WhatsApp session string...",
        required: true,
      },
      {
        key: "ownerNumber",
        envVar: "OWNER_NUMBER",
        label: "Owner Number",
        placeholder: "e.g. 254700000000 (with country code, no +)",
        required: true,
      },
    ],
  },

  cypherxultra: {
    name: "CypherX-Ultra",
    repoOwner: "Dark-Xploit",
    repoName: "CypherX-Ultra",
    branch: "main",
    containerStack: false,
    fields: [
      {
        key: "masterPassword",
        envVar: "MASTER_PASSWORD",
        label: "Master Password",
        placeholder: "secure password for the web dashboard",
        required: true,
        type: "password",
      },
      {
        key: "githubUsername",
        envVar: "GITHUB_USERNAME",
        label: "GitHub Username",
        placeholder: "your GitHub username (fork owner)",
        required: false,
      },
    ],
  },

  kingmd: {
    name: "King MD",
    repoOwner: "sesco001",
    repoName: "KING-MD",
    branch: "main",
    containerStack: true,
    fields: [
      {
        key: "session",
        envVar: "SESSION",
        label: "Session ID",
        placeholder: "paste WhatsApp session string...",
        required: true,
      },
      {
        key: "dev",
        envVar: "DEV",
        label: "Owner Number",
        placeholder: "e.g. 254700000000 (with country code, no +)",
        required: true,
      },
      {
        key: "code",
        envVar: "CODE",
        label: "Country Code",
        placeholder: "e.g. 254 for Kenya, 234 for Nigeria",
        required: true,
      },
    ],
  },

  anitav4: {
    name: "Queen Anitah",
    repoOwner: "Blurnk",
    repoName: "Anita-V4",
    branch: "main",
    containerStack: false,
    fields: [
      {
        key: "sessionId",
        envVar: "SESSION_ID",
        label: "Session ID",
        placeholder: "paste WhatsApp session string...",
        required: true,
      },
      {
        key: "ownerNumber",
        envVar: "OWNER_NUMBER",
        label: "Owner Number",
        placeholder: "e.g. 254700000000 (with country code, no +)",
        required: true,
      },
      {
        key: "prefix",
        envVar: "PREFIX",
        label: "Command Prefix",
        placeholder: ". or / or ! or #",
        required: false,
      },
      {
        key: "public",
        envVar: "PUBLIC",
        label: "Bot Mode",
        placeholder: "",
        required: true,
        type: "select",
        options: MODE_OPTIONS,
      },
      {
        key: "autoViewStatus",
        envVar: "AUTO_VIEW_STATUS",
        label: "Auto View Status",
        placeholder: "",
        required: true,
        type: "select",
        options: BOOL_OPTIONS,
      },
      {
        key: "antidelete",
        envVar: "ANTIDELETE",
        label: "Auto Delete Protection",
        placeholder: "",
        required: true,
        type: "select",
        options: BOOL_OPTIONS,
      },
      {
        key: "autoStatusReact",
        envVar: "AUTO_STATUS_REACT",
        label: "Auto React to Status",
        placeholder: "",
        required: true,
        type: "select",
        options: BOOL_OPTIONS,
      },
      {
        key: "chatbot",
        envVar: "CHATBOT",
        label: "Enable Chat Bot",
        placeholder: "",
        required: true,
        type: "select",
        options: BOOL_OPTIONS,
      },
    ],
  },

  atassa: {
    name: "Atassa MD",
    repoOwner: "mauricegift",
    repoName: "atassa",
    branch: "main",
    containerStack: false,
    fields: [
      {
        key: "sessionId",
        envVar: "SESSION_ID",
        label: "Session ID",
        placeholder: "paste WhatsApp session string...",
        required: true,
      },
      {
        key: "mode",
        envVar: "MODE",
        label: "Bot Mode",
        placeholder: "",
        required: true,
        type: "select",
        options: MODE_OPTIONS,
      },
      {
        key: "autoLikeStatus",
        envVar: "AUTO_LIKE_STATUS",
        label: "Auto Like Status",
        placeholder: "",
        required: true,
        type: "select",
        options: BOOL_OPTIONS,
      },
      {
        key: "autoReadStatus",
        envVar: "AUTO_READ_STATUS",
        label: "Auto Read Status",
        placeholder: "",
        required: true,
        type: "select",
        options: BOOL_OPTIONS,
      },
    ],
  },
};

export function getTarballUrl(bot: BotConfig): string {
  return `https://github.com/${bot.repoOwner}/${bot.repoName}/archive/refs/heads/${bot.branch}.tar.gz`;
}
