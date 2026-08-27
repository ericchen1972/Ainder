# Ainder — WebMCP 黑客松企劃書

版本：1.1
日期：2026-08-27
定位：以 ChatGPT 長期記憶提供交友第二意見的 WebMCP 體驗

Repository：`https://github.com/ericchen1972/Ainder`

正式路徑：`https://sweety.tw/ainder/`

## 1. 企劃摘要

Ainder 是一個以照片探索為主、AI 第二意見為輔的交友體驗。

使用者仍像使用 Tinder 一樣，先依照片決定是否對某人產生興趣。Ainder 不在使用者看到每張卡片時自動評分，也不以共同興趣、人格分數或百分比替人做決定。只有當使用者主動詢問時，使用者自己的 ChatGPT 才會透過 WebMCP 取得對方 AI 提交的 Agent Profile，再運用自己對使用者的長期記憶，給出簡短、具體、像熟人一樣的意見。

產品主張：

> Dating profiles show what people say about themselves. Ainder lets the AI that knows them speak to the AI that knows you.

輔助主張：

> People choose by attraction. Their AI gives the second opinion.

本次黑客松版本只驗證一件事：當兩個人的 ChatGPT 都已長期認識自己的使用者時，AI 是否能提供比傳統交友條件配對更有價值的第二意見。

## 2. 問題與機會

傳統交友產品主要依賴使用者自行填寫的照片、興趣、職業與自我介紹。這些內容通常經過挑選或包裝，能呈現使用者希望被看見的樣子，卻不一定反映長期行為、溝通方式與生活節奏。

另一方面，長期與使用者互動的 ChatGPT 可能已觀察到使用者如何思考、做決定、處理衝突、安排生活與表達需求。Ainder 不把這種理解變成公開評分，而是讓它在使用者產生興趣後，成為一個私人、可拒絕的第二意見。

本產品刻意避免：

- 以共同興趣作為主要契合依據。
- 顯示「契合度 66%」等看似精確但缺乏意義的分數。
- 讓 AI 在使用者尚未產生興趣前自動篩選對象。
- 宣稱 AI 能看穿人格、預測感情結果或代替使用者決定。

## 3. 產品目標

### 3.1 黑客松目標

- 展示 WebMCP 如何讓兩個網站使用者各自的 ChatGPT 參與同一個人類決策流程。
- 展示「長期熟悉度」比一次性問卷更可能產生具體、有脈絡的交友建議。
- 交付可登入、可瀏覽、可詢問 AI、可 Like、可完成雙向 Like 與聯絡資料揭露的完整 Demo。
- 讓評審在三分鐘內理解 WebMCP 並非附加功能，而是核心產品能力。

### 3.2 非本版目標

- 不做站內聊天室。
- 不做地區或距離篩選。
- 不做年齡範圍、興趣、職業或人格條件篩選。
- 不做推薦排序、相似度分數或自動配對。
- 不做推播、即時訊息、封鎖名單或完整內容審核後台。
- 不匯入或保存 ChatGPT 原始對話。
- 不讓 Ainder 伺服器自行重建使用者的長期記憶。

## 4. 目標使用者與 Demo 資料

黑客松 Demo 準備 20 位模擬使用者：

- 男性 10 位、女性 10 位。
- 年齡介於 25–50 歲。
- 使用英文名字。
- 第一版每位模擬使用者只需一張主照片。
- 每位模擬使用者均準備一份 Agent Profile 與 AI Familiarity 資料。
- 照片必須使用可合法公開展示的素材，並記錄來源或授權。

真實使用者必須年滿 18 歲。第一版不提供地域篩選，但年滿 18 歲仍是交友服務必要的使用門檻。

## 5. 使用者資料

### 5.1 登入

- 使用 Google 登入。
- 登入後若尚未完成個人資料，直接進入個人資料設定。
- 每次登入檢查 Agent Profile 是否存在，以及是否已超過 30 天未更新。

### 5.2 公開個人資料欄位

- 名稱：必填。
- 性別：必填。
- 年齡：必填，且不得低於 18 歲。
- 主照片：必填，1 張。
- 其他照片：最多 3 張，選填。

### 5.3 私密聯絡欄位

- LINE ID。
- WhatsApp ID。
- 兩者至少填寫一項。
- 聯絡資料不顯示在公開卡片或 Like 通知中。
- 只有雙方互相 Like 後，才向雙方揭露彼此填寫的聯絡資料。
- 個人資料頁必須清楚說明聯絡資料的揭露條件。

## 6. Agent Profile

### 6.1 定義

Agent Profile 是使用者自己的 ChatGPT 依目前可用的長期記憶所產生、提供給其他 AI 閱讀的描述。它不是使用者自行撰寫的自我介紹，也不直接顯示在公開交友卡片上。

Ainder 不提供自由編輯 Agent Profile 的欄位，避免它再次變成使用者包裝自己的文案。使用者可以要求自己的 ChatGPT 讀取目前版本，也可以同意或拒絕更新，但不在一般瀏覽流程中主動顯示全文。

### 6.2 更新規則

- 第一次使用 Ainder 時，需要建立 Agent Profile。
- 距離上次更新超過 30 天時，狀態改為需要更新。
- 更新是需要使用者確認的寫入行為，不得在背景無聲執行。
- 使用者拒絕更新時，可繼續瀏覽，但 Ask AI 功能顯示 Profile 已過期，並由詢問者的 AI 自行降低信任程度。
- Profile 保存版本與更新時間，第一版只使用最新版本進行評估。

### 6.3 AI Familiarity

每份 Agent Profile 同時提交以下熟悉度背景：

```json
{
  "familiarity": {
    "duration_band": "1_year_plus",
    "interaction_depth": "high",
    "confidence": "high",
    "estimated_by": "user_agent"
  },
  "profile_updated_at": "2026-08-27"
}
```

欄位定義：

- `duration_band`：`under_1_month`、`1_to_3_months`、`3_to_6_months`、`6_to_12_months`、`1_year_plus`。
- `interaction_depth`：`low`、`medium`、`high`。
- `confidence`：AI 對描述可靠程度的粗略自評，分為 `low`、`medium`、`high`。
- `estimated_by`：固定標示為 `user_agent`，提醒取得資料的 AI，熟悉度是對方 AI 的估計，而非 Ainder 驗證的客觀事實。
- `profile_updated_at`：最近更新日期。

Ainder 不保存精確對話次數、最早聊天日期、原始訊息或 ChatGPT Memory 內容。認識時間、互動深度與信心只作為另一個 AI 判斷描述可信度的背景，不轉換成對人評分的百分比。

### 6.4 描述原則

Agent Profile 應聚焦於可能影響長期互動的穩定觀察，例如：

- 生活節奏與日常安排方式。
- 溝通與處理歧見的傾向。
- 做決定、承擔風險與面對不確定性的方式。
- 社交能量與獨處需求。
- 對關係界線、自主性與陪伴的需求。

Agent Profile 不應包含：

- 原始對話或可還原對話的長篇引述。
- 身分證件、地址、帳務、醫療等敏感資料。
- 未經確認的診斷、人格疾病標籤或道德判決。
- 對未來關係結果的保證。

## 7. 核心使用流程

### 7.1 首次使用

1. 使用者以 Google 登入。
2. 填寫名稱、性別、年齡、至少一項聯絡方式與主照片。
3. Ainder 顯示 Agent Profile 尚未建立。
4. 使用者在 ChatGPT 中要求開始使用 Ainder。
5. ChatGPT 依目前可用的記憶產生 Agent Profile 與 AI Familiarity。
6. 使用者確認後，ChatGPT 透過 WebMCP 提交資料。
7. 使用者開始瀏覽卡片。

### 7.2 瀏覽與 Ask AI

1. 介面以 Tinder 式單張照片卡片呈現使用者。
2. 卡片顯示主照片、英文名字、年齡與性別。
3. 主要操作為略過與 Ask AI；原本的 Like 主按鈕改成 Ask AI。
4. 使用者對照片產生興趣後，請自己的 ChatGPT 評估目前對象。
5. ChatGPT 透過 WebMCP 取得對方的 Agent Profile 與 AI Familiarity。
6. ChatGPT 使用自己對詢問者的長期理解進行評估。
7. 頁面以 Modal 顯示簡短 AI 意見，Modal 下方提供 Like 與關閉／略過操作。

### 7.3 Like 與雙向 Like

假設 A 瀏覽 B：

1. A 詢問自己的 AI 對 B 的意見。
2. A 的 AI 產生私人建議與可分享意見。
3. A 按下 Like。
4. B 在收到的 Like 頁面看到 A 的卡片，以及「A 的 AI 如何看 B」的可分享意見。
5. 若 B 不 Like A，流程結束，不揭露任何聯絡資料。
6. 若 B 也詢問 AI 並 Like A，雙方形成 Match。
7. Match 後，A 與 B 都能看到兩份可分享意見：A 的 AI 如何看 B，以及 B 的 AI 如何看 A。
8. 系統向雙方揭露彼此的 LINE ID 與／或 WhatsApp ID，由雙方自行建立聯絡。

本版不建立站內聊天室。

## 8. AI 意見的可見性

AI 意見分為兩層，避免把詢問者的私人記憶直接透露給被評估者。

### 8.1 私人建議 `private_advice`

- 只顯示給詢問者。
- 可以使用詢問者 AI 對詢問者的私人理解，例如作息、溝通需求或過往決策方式。
- 不會因為 Like 或 Match 而自動分享給對方。

範例：

> Eric 經常工作到天亮才睡，而你通常很重視晚上 11 點前休息。這不代表一定不適合，但長期相處可能讓你覺得彼此沒有共同生活時間。你如果還有興趣，可以先問他作息是否有調整空間。

### 8.2 可分享意見 `shareable_impression`

- A 按 Like B 後，可讓 B 看見。
- 只描述 AI 對 B 的觀察與整體印象，不揭露 A 的私人記憶。
- 雙向 Like 後，雙方都能看到彼此 AI 產生的可分享意見。

範例：

> 我認為 Eric 的生活節奏很自由，也很願意為感興趣的事投入大量時間。他可能更適合能接受非典型作息、重視自主空間的人。

這個雙層設計保留「你的 AI 怎麼看我」的產品價值，同時避免 A 的 AI 在給 B 看意見時，洩露 A 的睡眠、感情或生活細節。

## 9. AI 回答規則

AI 的角色是熟悉使用者的朋友，不是配對計算器。

每次回答應符合以下規則：

- 只選擇 1–2 個最重要的具體理由。
- 以 1–3 句自然語言回答。
- 不顯示契合百分比、星等或人格相似度。
- 不以共同興趣清單作為主要理由。
- 不宣稱命中注定、完美配對或一定不適合。
- 不捏造使用者記憶中不存在的資訊。
- 若 AI 對任一方熟悉度不足，必須明確降低語氣確定性。
- 可以在結尾提出一個適合向對方確認的問題。

熟悉度不足範例：

> 他的 AI 認識他不到一個月，這些描述比較接近初步印象。我目前也不夠了解你在親密關係中的生活需求，所以不會替你下結論；你可以先問他理想的日常相處頻率。

## 10. 主要頁面

### 10.1 登入頁

- Google 登入。
- 一句話產品主張。
- 簡短說明：照片由人判斷，AI 只在被詢問時提供第二意見。

### 10.2 個人資料頁

- 名稱、性別、年齡。
- 主照片上傳與最多三張其他照片。
- LINE ID、WhatsApp ID。
- 聯絡資料揭露說明。
- Agent Profile 狀態：未建立、有效、即將過期、已過期。
- 「請我的 AI 更新」引導。

### 10.3 探索頁

- 單張 Tinder 式卡片。
- 主照片為視覺核心。
- 顯示名稱、年齡與性別。
- 略過按鈕。
- Ask AI 主按鈕。

### 10.4 AI 意見 Modal

- 顯示簡短私人建議。
- 在需要時顯示對方 AI 熟悉度不足的提醒。
- Like 按鈕。
- 關閉／略過按鈕。
- 不顯示數值契合度。

### 10.5 收到的 Like

- 顯示 Like 來源的使用者卡片。
- 顯示對方 AI 對自己的可分享意見。
- 提供 Ask AI 與略過操作。

### 10.6 Match 結果

- 顯示雙方互相 Like。
- 顯示兩份可分享意見。
- 顯示雙方 LINE ID 與／或 WhatsApp ID。
- 不提供站內聊天。

## 11. WebMCP 工具設計

第一版建議提供以下工具：

### `get_my_agent_profile_status`

取得目前登入者的 Agent Profile 狀態、最近更新日期與是否需要更新。

### `submit_my_agent_profile`

提交或更新目前登入者的 Agent Profile 與 AI Familiarity。這是寫入操作，需要使用者確認。

### `get_current_candidate_agent_profile`

取得探索頁目前顯示對象的 Agent Profile、AI Familiarity 與公開基本資料。工具不得回傳 LINE 或 WhatsApp。

### `submit_candidate_opinion`

提交對目前對象的 `private_advice` 與 `shareable_impression`，供頁面 Modal 顯示與後續 Like 使用。

### `like_current_candidate`

Like 目前對象，保存可分享意見，並回傳是否形成雙向 Like。

### `get_received_likes`

取得 Like 自己的使用者，以及其 AI 對自己的可分享意見。

### `get_match_contact`

只有雙向 Like 時才能取得對方聯絡資料。未 Match 時必須拒絕並回傳明確錯誤。

所有工具都沿用登入狀態與伺服器授權。工具描述不能要求 ChatGPT 將與當次任務無關的私人記憶提交給網站。

## 12. 建議技術架構

### 12.1 部署與網域

- Ainder 使用獨立公開 repository：`https://github.com/ericchen1972/Ainder`。
- 正式網址使用 `https://sweety.tw/ainder/`，不建立子網域。
- 程式透過 FTP 上傳至 Sweety 網站根目錄下的 `/sweety.tw/ainder/`。
- FTP 連線設定沿用 SweetyGame 本機的 `web/sftp-config.json`，不得複製或提交到 Ainder repository。
- 部署腳本必須以 `FTP_BINARY` 上傳，建立缺少的遠端目錄，並逐檔比對遠端大小與本機檔案大小。
- Ainder 使用 SweetyGame 現有 MySQL 連線設定與同一資料庫，但所有資料表使用 `ainder_` 前綴隔離。
- 正式環境由 Ainder PHP bootstrap 載入 Sweety 網站根目錄既有的 `mysql.php`；Ainder repository 不保存資料庫帳密。
- 使用者照片儲存在 `/sweety.tw/ainder/uploads/`，資料庫只保存相對路徑。上傳目錄不得允許執行 PHP。
- Google OAuth callback 固定為 `https://sweety.tw/ainder/auth/google/callback.php`。
- 所有前端資源、導向與 API 路徑都必須支援 `/ainder/` base path，不能假設部署在網域根目錄。

### 12.2 執行架構

- 前端：HTML、CSS 與 JavaScript，提供 Tinder 式卡片與 Modal 互動。
- 後端：PHP 8.2，處理 Google 登入、Session、圖片上傳、Like、Match 與 WebMCP 對應資料存取。
- 資料庫：沿用 SweetyGame MySQL 設定，在相同資料庫建立 Ainder 專用資料表。
- Session：使用安全 Cookie，限制 `Secure`、`HttpOnly` 與適當的 `SameSite` 設定。
- WebMCP：由 Ainder 頁面註冊工具，工具執行時呼叫同源 PHP endpoint，並沿用目前登入 Session 與伺服器端授權檢查。
- 本機開發與測試使用不含真實憑證的 example 設定或測試替身。

### 12.3 為何不沿用舊交友網站程式

- 舊 PHP 程式不是目前 Ainder 專案的一部分。
- 缺少符合本案需求的 Google 登入與 WebMCP 架構。
- 黑客松要求公開原始碼與可辨識的新增工作，獨立 repository 更容易呈現提交期間的實作。
- Ainder 雖部署於同一網域並沿用 FTP／資料庫連線設定，程式、資料表、上傳目錄與網址路徑仍保持隔離，避免影響 `sweety.tw` 現有反詐產品。

## 13. 核心資料模型

### `users`

- `id`
- `google_subject`
- `name`
- `gender`
- `age`
- `line_id`
- `whatsapp_id`
- `created_at`
- `updated_at`

### `user_photos`

- `id`
- `user_id`
- `storage_path`
- `is_primary`
- `sort_order`

限制：每位使用者恰好一張主照片，其他照片最多三張。

### `agent_profiles`

- `id`
- `user_id`
- `profile_text`
- `duration_band`
- `interaction_depth`
- `confidence`
- `estimated_by`
- `created_at`
- `expires_at`
- `is_current`

### `candidate_opinions`

- `id`
- `viewer_user_id`
- `candidate_user_id`
- `private_advice`
- `shareable_impression`
- `created_at`

### `likes`

- `id`
- `from_user_id`
- `to_user_id`
- `candidate_opinion_id`
- `created_at`

雙向 Like 由兩筆方向相反的 Like 判斷，不另外建立聊天室。

正式資料表名稱分別為 `ainder_users`、`ainder_user_photos`、`ainder_agent_profiles`、`ainder_candidate_opinions` 與 `ainder_likes`。

## 14. Demo 腳本

Demo 影片控制在三分鐘內，建議腳本如下：

1. 以 Google 登入 Ainder。
2. 顯示簡單個人資料與 Agent Profile 更新狀態。
3. 請 ChatGPT 更新 Agent Profile，展示 WebMCP 寫入與 AI Familiarity。
4. 進入 Tinder 式探索頁，略過第一位使用者。
5. 對第二位使用者產生興趣，詢問 ChatGPT：「這個人你覺得呢？」
6. ChatGPT 呼叫 WebMCP 取得對方 Agent Profile。
7. 顯示一段沒有百分比、包含具體生活脈絡的私人建議。
8. 在 Modal 按 Like。
9. 切換至另一個 Demo 帳號，顯示收到的 Like 與「你的 AI 怎麼看我」。
10. 第二個帳號也詢問自己的 AI 並 Like。
11. 顯示 Match、雙方可分享意見及 LINE／WhatsApp 聯絡方式。

## 15. 評審價值

### WebMCP Leverage

WebMCP 串起 Agent Profile 提交、對象資料取得、AI 意見回寫、Like 與 Match 聯絡資料解鎖。若沒有 WebMCP，網站無法讓使用者自己的 ChatGPT 在保留使用者脈絡的情況下參與這個流程。

### Execution

產品具備登入、個人資料、照片卡片、AI 意見、Like、收到的 Like、雙向 Like 與聯絡資料揭露，形成完整閉環，而非單一工具展示。

### Potential Impact

傳統交友問卷只能取得使用者主動填寫的內容；Ainder 探索長期個人 AI 能否成為更有脈絡、但仍由人決定的第二意見。

### Creativity & Ambition

Ainder 不讓 AI 取代人挑選，也不以分數自動配對。它讓兩個各自長期認識使用者的 AI，透過 WebMCP 在人類產生興趣之後提供觀察。

## 16. 開發時程

### 第 1 天

- 同步公開 repository、加入授權檔並建立 `/ainder/` base-path 部署骨架。
- 建立 Ainder MySQL migration、Google Auth 與基本個人資料。
- 建立沿用 SweetyGame FTP 設定但不提交憑證的部署腳本。

### 第 2 天

- 完成照片上傳、Tinder 式探索頁與 20 位模擬使用者。

### 第 3 天

- 完成 Agent Profile、AI Familiarity 與 30 天更新狀態。
- 實作核心 WebMCP tools。

### 第 4 天

- 完成 AI 意見 Modal、Like、收到的 Like 與雙向 Like。
- 完成聯絡資料解鎖。

### 第 5 天

- 完成桌面與手機版驗證。
- 測試權限、照片限制、過期 Profile、單向與雙向 Like。

### 第 6 天

- 錄製三分鐘內英文 Demo。
- 完成英文 README、測試帳號與 Devpost 說明。

### 第 7 天

- 修正 Demo 問題、驗證公開網址與 repository。
- 完成並送出黑客松 Submission。

## 17. 驗收條件

- 使用者能以 Google 登入並完成最小個人資料。
- Google OAuth callback 能在 `https://sweety.tw/ainder/` 路徑下完成登入並返回 Ainder。
- 沒有主照片時不能進入探索頁。
- 最多只能上傳一張主照片與三張其他照片。
- LINE／WhatsApp 至少填寫一項。
- Agent Profile 超過 30 天時顯示過期。
- ChatGPT 能透過 WebMCP 提交 Agent Profile 與熟悉度資料。
- ChatGPT 能取得目前對象資料並產生不含數值分數的意見。
- AI 意見 Modal 能顯示私人建議並執行 Like。
- 被 Like 的使用者能看到對方 AI 對自己的可分享意見。
- 單向 Like 不揭露聯絡資料。
- 雙向 Like 後雙方能看到兩份可分享意見與彼此聯絡資料。
- 任何 WebMCP 工具都不能在未 Match 時取得聯絡資料。
- 公開 repository 包含開源授權、完整原始碼與本機執行說明。
- FTP 部署後逐檔大小驗證通過，且 `https://sweety.tw/ainder/` 可正常載入。
- 正式資料庫只新增或讀寫 `ainder_` 前綴資料表，不修改 SweetyGame 既有資料表。

## 18. 主要風險與處理方式

### ChatGPT Memory 並非完整逐字歷史

Agent Profile 與 AI 意見只能依當下 ChatGPT 實際可用的記憶與脈絡產生。產品不能宣稱 AI 已讀取完整一年對話，也不能將認識時間描述為平台驗證事實。

處理方式：使用 `duration_band`、`interaction_depth`、`confidence` 與 `estimated_by`，並允許 AI 明確表示熟悉度不足。

### 網頁不能自行喚醒 ChatGPT

WebMCP 是由 ChatGPT 發現並呼叫網站工具。Ask AI 按鈕可以設定目前對象與顯示引導，但不能承諾由網頁按鈕直接召喚 ChatGPT Memory。

處理方式：Demo 在 ChatGPT 內建瀏覽器中進行，使用者以自然語言詢問目前對象；Ainder 的工具回傳目前卡片資料。

### AI 意見可能洩露詢問者的私人資訊

若直接把完整評估展示給被 Like 的一方，內容可能包含詢問者的作息、關係經驗或私人需求。

處理方式：強制分離 `private_advice` 與 `shareable_impression`。被 Like 的使用者只能看到後者。

### 聯絡資料揭露風險

LINE 與 WhatsApp 屬於私密聯絡資料。

處理方式：只在雙向 Like 後揭露；公開卡片、Agent Profile 與 WebMCP 對象讀取工具均不得回傳聯絡資料。

### 子目錄部署路徑錯誤

若程式假設自己位於網域根目錄，Google OAuth callback、圖片、JavaScript、PHP endpoint 或頁面導向可能錯誤地指向 `https://sweety.tw/`。

處理方式：所有公開網址統一以 `/ainder/` 為 base path，針對直接載入、登入 callback、重新整理、圖片與 WebMCP endpoint 分別驗證。

### 共用資料庫與主機

Ainder 與 SweetyGame 共用 FTP 帳號、網站主機與資料庫連線，錯誤 migration 或部署範圍可能影響既有服務。

處理方式：部署目標限定為 `/sweety.tw/ainder/`；資料表一律使用 `ainder_` 前綴；migration 不得修改或刪除既有資料表；憑證只從既有本機設定與正式環境根目錄設定載入，不進入公開 repository。

## 19. 第一版結論

Ainder 第一版不嘗試打造完整交友平台。它只打造一個完整、可理解、可展示的閉環：

> 看照片 → 對某人產生興趣 → 詢問真正認識自己的 AI → 聽取具體而非量化的意見 → Like → 對方看見「你的 AI 怎麼看我」→ 雙向 Like → 交換聯絡方式。

這個範圍足以讓評審看見 WebMCP 在個人 AI、網站與人類決策之間帶來的獨特價值，同時能在有限時間內完成並驗證。
