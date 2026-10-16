"use strict";

const messageList = document.querySelector("#messages");
const search = document.querySelector("#search");
const refresh = document.querySelector("#refresh");
const status = document.querySelector("#status");
const error = document.querySelector("#error");
const empty = document.querySelector("#empty");
const states = {
    sms: {messages: [], loading: false, updated: null, error: null},
    emails: {messages: [], loading: false, updated: null, error: null},
};
let activeType = "sms";

function textElement(tag, content, className = "") {
    const element = document.createElement(tag);
    element.textContent = content;
    element.className = className;
    return element;
}

function messageTime(timestamp) {
    const time = textElement("time", new Date(timestamp).toLocaleString());
    time.dateTime = timestamp;
    return time;
}

function searchableText(message) {
    if (activeType === "sms") return `${message.recipient} ${message.message}`;
    return [message.subject, message.sender, ...message.to, ...message.cc, ...message.bcc, message.text].join(" ");
}

function renderMessages() {
    const messages = states[activeType].messages;
    const query = search.value.trim().toLocaleLowerCase();
    const visible = messages.filter(message => searchableText(message).toLocaleLowerCase().includes(query));
    document.querySelector("#count").textContent = `${visible.length} of ${messages.length}`;
    empty.hidden = visible.length > 0;
    empty.textContent = emptyMessage(query);
    const openEmails = new Set([...messageList.querySelectorAll("details[open]")].map(item => item.dataset.id));
    const rows = visible.map(message => activeType === "sms" ? smsRow(message) : emailRow(message, openEmails));
    messageList.replaceChildren(...rows);
}

function emptyMessage(query) {
    if (query) return "No messages match this filter.";
    if (activeType === "emails") return "No emails captured by MiniStack. Send an email verification request through the local API.";
    return "No SMS messages in the current log window. Send a mobile verification request through the local API.";
}

function smsRow(message) {
    const row = document.createElement("li");
    const heading = document.createElement("div");
    heading.className = "message-heading";
    heading.append(textElement("span", message.recipient, "recipient"), messageTime(message.timestamp));
    row.append(heading, textElement("p", message.message, "message"));
    return row;
}

function emailRow(message, openEmails) {
    const row = document.createElement("li");
    const details = document.createElement("details");
    details.dataset.id = message.id;
    details.open = openEmails.has(message.id);
    const summary = document.createElement("summary");
    const heading = document.createElement("span");
    heading.className = "message-heading";
    heading.append(textElement("span", message.subject || "(No subject)", "email-subject"), messageTime(message.timestamp));
    summary.append(heading);
    const recipients = message.to.join(", ") || "Recipient not recorded by MiniStack";
    summary.append(textElement("p", recipients, "email-recipient"));
    details.append(summary, emailMetadata(message));
    details.append(textElement("p", "Email preview · Scripts, links, and remote images are blocked.", "preview-note"));
    const frame = document.createElement("iframe");
    frame.title = `Preview: ${message.subject || "Email"}`;
    frame.setAttribute("sandbox", "");
    frame.referrerPolicy = "no-referrer";
    details.append(frame);
    details.addEventListener("toggle", () => loadPreview(details, frame, message.id));
    loadPreview(details, frame, message.id);
    row.append(details);
    return row;
}

function loadPreview(details, frame, identifier) {
    if (!details.open || frame.hasAttribute("src")) return;
    frame.src = `/email-preview?id=${encodeURIComponent(identifier)}`;
}

function emailMetadata(message) {
    const metadata = document.createElement("dl");
    metadata.className = "email-metadata";
    const fields = [["From", message.sender], ["To", message.to.join(", ") || "Not recorded by MiniStack"]];
    if (message.cc.length) fields.push(["Cc", message.cc.join(", ")]);
    if (message.bcc.length) fields.push(["Bcc", message.bcc.join(", ")]);
    for (const [name, value] of fields) metadata.append(textElement("dt", name), textElement("dd", value));
    return metadata;
}

function renderStatus() {
    const state = states[activeType];
    refresh.disabled = state.loading;
    error.hidden = !state.error;
    error.textContent = state.error || "";
    status.textContent = state.updated ? `Updated ${state.updated.toLocaleTimeString()}` : "Loading messages…";
    if (state.error) status.textContent = "Update failed. Retrying every 5 seconds.";
}

async function loadMessages() {
    const type = activeType;
    const state = states[type];
    if (state.loading) return;
    state.loading = true;
    renderStatus();
    try {
        const path = type === "sms" ? "/messages" : "/emails";
        const response = await fetch(path, {cache: "no-store", signal: AbortSignal.timeout(7000)});
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.error);
        const messages = type === "sms" ? payload.messages : payload.emails;
        const changed = JSON.stringify(messages) !== JSON.stringify(state.messages);
        state.messages = messages;
        state.updated = new Date();
        state.error = null;
        if (type === activeType && (changed || messages.length === 0)) renderMessages();
    } catch (exception) {
        state.error = exception.message || "Cannot load messages. Check the viewer server, then select Refresh.";
    } finally {
        state.loading = false;
        if (type === activeType) renderStatus();
    }
}

function showMessages(type) {
    activeType = type;
    document.querySelector("#show-sms").setAttribute("aria-pressed", String(type === "sms"));
    document.querySelector("#show-emails").setAttribute("aria-pressed", String(type === "emails"));
    document.querySelector("#list-title").textContent = type === "sms" ? "SMS messages" : "Emails · Select a message to preview";
    search.placeholder = type === "sms" ? "Phone number or message" : "Subject, address, or plain text";
    document.querySelector("#capture-limit").textContent = type === "sms"
        ? "MiniStack logs up to 80 characters. This viewer shows the first line of each SMS, from the latest 2,000 log lines, up to 200 messages. Logs can disappear when the container is recreated."
        : "Shows up to 200 captured SES emails. Some raw emails have no stored recipient. Previews use the stored HTML or plain text. Attachments and remote images are not loaded. Captured messages do not prove inbox delivery.";
    renderMessages();
    renderStatus();
    loadMessages();
}

search.addEventListener("input", renderMessages);
refresh.addEventListener("click", loadMessages);
document.querySelector("#show-sms").addEventListener("click", () => showMessages("sms"));
document.querySelector("#show-emails").addEventListener("click", () => showMessages("emails"));
loadMessages();
setInterval(loadMessages, 5000);
