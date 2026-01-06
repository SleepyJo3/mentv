(() => {
  const form = document.getElementById("orderForm");
  const statusEl = document.getElementById("status");
  const typeEl = document.getElementById("type");
  const renewField = document.getElementById("renewUserField");
  const renewInput = document.getElementById("renew_username");
  const telegramPrefill = document.getElementById("telegramPrefill");

  // OPTIONAL: your Telegram public username OR bot deep link for prefill fallback
  const TELEGRAM_PUBLIC_LINK = "https://t.me/YOUR_TELEGRAM_USERNAME_OR_BOT";

  function buildMessage(data){
    const lines = [
      "MENIPTV ORDER",
      `Type: ${data.type}`,
      `Plan: ${data.plan}`,
      `Devices: ${data.devices}`,
      data.app ? `App/Device: ${data.app}` : null,
      data.renew_username ? `Renew Username: ${data.renew_username}` : null,
      `Contact: ${data.contact}`,
      `Time: ${new Date().toISOString()}`
    ].filter(Boolean);
    return lines.join("\n");
  }

  function updateRenewUI(){
    const isRenew = typeEl.value === "Renewal";
    renewField.classList.toggle("hidden", !isRenew);
    renewInput.required = isRenew;
  }

  function setTelegramPrefill(data){
    const text = encodeURIComponent(buildMessage(data));
    telegramPrefill.href = `${TELEGRAM_PUBLIC_LINK}?text=${text}`;
  }

  updateRenewUI();
  typeEl.addEventListener("change", updateRenewUI);

  ["plan","devices","type","app","contact","renew_username"].forEach(id => {
    const el = document.getElementById(id);
    if (!el) return;
    const handler = () => {
      const data = Object.fromEntries(new FormData(form).entries());
      setTelegramPrefill(data);
    };
    el.addEventListener("input", handler);
    el.addEventListener("change", handler);
  });

  form.addEventListener("submit", async (e) => {
    e.preventDefault();
    statusEl.textContent = "";

    // Honeypot
    if ((document.getElementById("company").value || "").trim() !== "") {
      statusEl.textContent = "Error. Please try again.";
      return;
    }

    const data = Object.fromEntries(new FormData(form).entries());

    if (!data.plan || !data.devices || !data.type || !data.contact) {
      statusEl.textContent = "Please fill required fields.";
      return;
    }
    if (data.type === "Renewal" && !data.renew_username) {
      statusEl.textContent = "Please add your renewal username.";
      return;
    }

    setTelegramPrefill(data);

    try {
      statusEl.textContent = "Sending…";

      const res = await fetch("/api/send-order.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          ...data,
          message: buildMessage(data)
        })
      });

      const json = await res.json().catch(() => ({}));

      if (!res.ok || !json.ok) {
        statusEl.textContent = json.error || "Send failed. Open Telegram and send the prefilled message.";
        return;
      }

      statusEl.textContent = "✅ Order sent! We’ll reply shortly.";
      form.reset();
      updateRenewUI();

    } catch (err) {
      statusEl.textContent = "Network error. Open Telegram and send the prefilled message.";
    }
  });
})();
