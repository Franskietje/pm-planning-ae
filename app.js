document.getElementById("testBtn").addEventListener("click", async () => {
  const output = document.getElementById("output");
  output.textContent = "Bezig...";

  try {
    const res = await fetch("https://withered-feather-3456.sd-5bd.workers.dev/test");
    const data = await res.json();
    output.textContent = JSON.stringify(data, null, 2);
  } catch (err) {
    output.textContent = "Fout: " + err.message;
  }
});
