using System;
using System.Collections;
using System.Collections.Generic;
using System.Drawing;
using System.IO;
using System.Net;
using System.Text;
using System.Threading.Tasks;
using System.Web.Script.Serialization;
using System.Windows.Forms;

namespace EraportDapodikBridgeHelper
{
    internal static class Program
    {
        [STAThread]
        private static void Main()
        {
            Application.EnableVisualStyles();
            Application.SetCompatibleTextRenderingDefault(false);
            Application.Run(new MainForm());
        }
    }

    internal sealed class MainForm : Form
    {
        private const string AppVersion = "v2.5";
        private const string ConfigMarker = "__ERAPORT_BRIDGE_CONFIG__";
        private const string ConfigEndMarker = "__END_ERAPORT_BRIDGE_CONFIG__";
        private const string PortableConfigFileName = "eraport-bridge-config.txt";
        private static readonly string[] DefaultSyncTypes = new[] { "sekolah", "guru", "rombel", "siswa", "anggota_rombel", "pembelajaran" };

        private readonly TextBox dapodikUrlText = new TextBox();
        private readonly TextBox dapodikTokenText = new TextBox();
        private readonly TextBox npsnText = new TextBox();
        private readonly TextBox bridgeUrlText = new TextBox();
        private readonly TextBox bridgeTokenText = new TextBox();
        private readonly ComboBox typeCombo = new ComboBox();
        private readonly Button syncAllButton = new Button();
        private readonly Button syncSelectedButton = new Button();
        private readonly Button saveButton = new Button();
        private readonly TextBox logText = new TextBox();

        private readonly JavaScriptSerializer serializer = new JavaScriptSerializer { MaxJsonLength = int.MaxValue };

        private readonly Dictionary<string, string[]> dapodikEndpoints = new Dictionary<string, string[]>
        {
            { "sekolah", new[] { "getSekolah" } },
            { "guru", new[] { "getGtk" } },
            { "siswa", new[] { "getPesertaDidik" } },
            { "anggota_rombel", new[] { "getAnggotaRombel", "getAnggotaRombonganBelajar", "getPesertaDidikRombel" } },
            { "mapel", new[] { "getMataPelajaran" } },
            { "rombel", new[] { "getRombonganBelajar" } },
            { "pembelajaran", new[] { "getPembelajaran", "getPembelajaranGuru", "getDataPembelajaran" } }
        };

        public MainForm()
        {
            Text = "E-Raport Dapodik Bridge Helper " + AppVersion;
            StartPosition = FormStartPosition.CenterScreen;
            MinimumSize = new Size(900, 680);
            Size = new Size(980, 760);

            BuildLayout();
            LoadDefaults();
        }

        private void BuildLayout()
        {
            var root = new TableLayoutPanel
            {
                Dock = DockStyle.Fill,
                ColumnCount = 1,
                RowCount = 4,
                Padding = new Padding(18)
            };
            root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            root.RowStyles.Add(new RowStyle(SizeType.AutoSize));
            root.RowStyles.Add(new RowStyle(SizeType.Percent, 100));
            Controls.Add(root);

            var title = new Label
            {
                Text = "E-Raport Dapodik Bridge Helper " + AppVersion,
                Dock = DockStyle.Top,
                Font = new Font(FontFamily.GenericSansSerif, 15, FontStyle.Bold),
                AutoSize = true,
                Margin = new Padding(0, 0, 0, 10)
            };
            root.Controls.Add(title, 0, 0);

            var formGrid = new TableLayoutPanel
            {
                Dock = DockStyle.Top,
                ColumnCount = 2,
                AutoSize = true
            };
            formGrid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
            formGrid.ColumnStyles.Add(new ColumnStyle(SizeType.Percent, 50));
            root.Controls.Add(formGrid, 0, 1);

            AddField(formGrid, "URL Dapodik Lokal", dapodikUrlText, 0, 0);
            AddField(formGrid, "Token Web Service / Token Sinkron", dapodikTokenText, 1, 0);
            AddField(formGrid, "NPSN", npsnText, 0, 1);
            AddCombo(formGrid, "Jenis Data", typeCombo, 1, 1);
            AddField(formGrid, "Link Web Raport", bridgeUrlText, 0, 2);

            typeCombo.DropDownStyle = ComboBoxStyle.DropDownList;
            typeCombo.Items.Add(new TypeOption("all", "Semua Data Dasar"));
            typeCombo.Items.Add(new TypeOption("sekolah", "Sekolah"));
            typeCombo.Items.Add(new TypeOption("guru", "Guru/GTK"));
            typeCombo.Items.Add(new TypeOption("siswa", "Siswa"));
            typeCombo.Items.Add(new TypeOption("anggota_rombel", "Anggota Rombel"));
            typeCombo.Items.Add(new TypeOption("mapel", "Mapel"));
            typeCombo.Items.Add(new TypeOption("rombel", "Rombel"));
            typeCombo.Items.Add(new TypeOption("pembelajaran", "Pembelajaran"));
            typeCombo.SelectedIndex = 0;

            var buttons = new FlowLayoutPanel
            {
                Dock = DockStyle.Top,
                AutoSize = true,
                FlowDirection = FlowDirection.LeftToRight,
                Margin = new Padding(0, 14, 0, 14)
            };
            root.Controls.Add(buttons, 0, 2);

            syncAllButton.Text = "Sinkron Data Dasar";
            syncAllButton.Width = 180;
            syncAllButton.Height = 38;
            syncAllButton.Click += delegate { RunSync(true); };
            buttons.Controls.Add(syncAllButton);

            syncSelectedButton.Text = "Sinkron Pilihan";
            syncSelectedButton.Width = 150;
            syncSelectedButton.Height = 38;
            syncSelectedButton.Click += delegate { RunSync(false); };
            buttons.Controls.Add(syncSelectedButton);

            saveButton.Text = "Simpan Konfigurasi";
            saveButton.Width = 170;
            saveButton.Height = 38;
            saveButton.Click += delegate
            {
                SaveConfig();
                AppendLog("Konfigurasi tersimpan di " + ActiveConfigPath());
            };
            buttons.Controls.Add(saveButton);

            logText.Dock = DockStyle.Fill;
            logText.Multiline = true;
            logText.ScrollBars = ScrollBars.Vertical;
            logText.ReadOnly = true;
            logText.Font = new Font(FontFamily.GenericMonospace, 10);
            root.Controls.Add(logText, 0, 3);
        }

        private static void AddField(TableLayoutPanel grid, string label, TextBox textBox, int column, int row)
        {
            var panel = CreateFieldPanel(label);
            textBox.Dock = DockStyle.Top;
            textBox.Height = 28;
            panel.Controls.Add(textBox);
            grid.Controls.Add(panel, column, row);
        }

        private static void AddCombo(TableLayoutPanel grid, string label, ComboBox comboBox, int column, int row)
        {
            var panel = CreateFieldPanel(label);
            comboBox.Dock = DockStyle.Top;
            comboBox.Height = 30;
            panel.Controls.Add(comboBox);
            grid.Controls.Add(panel, column, row);
        }

        private static Panel CreateFieldPanel(string label)
        {
            var panel = new Panel
            {
                Dock = DockStyle.Top,
                Height = 72,
                Padding = new Padding(0, 0, 10, 10)
            };
            var labelControl = new Label
            {
                Text = label,
                Dock = DockStyle.Top,
                Height = 24,
                Font = new Font(FontFamily.GenericSansSerif, 9, FontStyle.Bold)
            };
            panel.Controls.Add(labelControl);
            return panel;
        }

        private void LoadDefaults()
        {
            dapodikUrlText.Text = "http://127.0.0.1:5774";
            SelectType("all");

            var portableConfig = LoadConfigFile(PortableConfigPath());
            if (portableConfig.Count > 0)
            {
                ApplyConfig(portableConfig, true);
                AppendLog("Mode portable aktif. Config: " + PortableConfigPath());
                return;
            }

            ApplyConfig(LoadConfigFile(LegacyConfigPath()), false);
            var embeddedConfig = ReadEmbeddedConfig();
            ApplyConfig(embeddedConfig, true);
            if (embeddedConfig.Count > 0)
            {
                try
                {
                    SaveConfig();
                    AppendLog("Config portable dibuat di " + ActiveConfigPath());
                }
                catch (Exception ex)
                {
                    AppendLog("Config belum bisa disimpan otomatis: " + ex.Message);
                }
            }
        }

        private void RunSync(bool forceAll)
        {
            SaveConfig();
            SetBusy(true);
            logText.Clear();
            AppendLog("Mulai sinkron...");

            Task.Factory.StartNew(delegate
            {
                try
                {
                    var types = forceAll || SelectedType() == "all"
                        ? DefaultSyncTypes
                        : new[] { SelectedType() };

                    var hasError = false;
                    foreach (var type in types)
                    {
                        try
                        {
                            SyncType(type);
                        }
                        catch (Exception typeEx)
                        {
                            if (IsOptionalEndpointMissing(type, typeEx.Message))
                            {
                                AppendLog(type + ": dilewati. Endpoint ini tidak tersedia di Web Service Dapodik lokal yang sedang aktif.");
                                continue;
                            }

                            hasError = true;
                            AppendLog(type + ": gagal, " + typeEx.Message);
                            if (IsBridgeTokenError(typeEx.Message))
                            {
                                break;
                            }
                        }
                    }

                    AppendLog(hasError ? "Selesai dengan error. Cek log di atas." : "Selesai.");
                }
                catch (Exception ex)
                {
                    AppendLog("Gagal: " + ex.Message);
                }
                finally
                {
                    SetBusy(false);
                }
            });
        }

        private void SyncType(string type)
        {
            var dapodikUrl = NormalizeUrl(GetText(dapodikUrlText));
            var dapodikToken = GetText(dapodikTokenText);
            var npsn = GetText(npsnText);
            var bridgeUrl = NormalizeBridgeUrl(GetText(bridgeUrlText));
            var bridgeToken = dapodikToken;

            if (dapodikToken.Length == 0 || npsn.Length == 0)
            {
                throw new InvalidOperationException("Token Dapodik dan NPSN wajib diisi.");
            }
            if (bridgeUrl.Length == 0 || bridgeToken.Length == 0)
            {
                throw new InvalidOperationException("Link Web Raport dan Token Web Service Dapodik wajib diisi.");
            }

            var endpoints = BuildDapodikEndpoints(dapodikUrl, type, npsn, null);
            var fallbackEndpoints = BuildDapodikEndpoints(dapodikUrl, type, npsn, dapodikToken);
            var endpoint = endpoints[0];
            object records = null;
            string authLabel = "";
            var errors = new List<string>();
            var found = false;

            for (var i = 0; i < endpoints.Count; i++)
            {
                try
                {
                    endpoint = endpoints[i];
                    AppendLog(type + ": membaca " + endpoint + " [Authorization Bearer]");
                    var dapodikBody = HttpGet(endpoint, dapodikToken);
                    records = ExtractRecords(dapodikBody);
                    authLabel = "Authorization Bearer";
                    found = true;
                    break;
                }
                catch (Exception bearerEx)
                {
                    var fallbackEndpoint = fallbackEndpoints[i];
                    if (IsOptionalEndpointMissingResponse(type, bearerEx.Message))
                    {
                        errors.Add(Path.GetFileName(endpoint) + " Bearer: " + bearerEx.Message);
                        AppendLog(type + ": endpoint " + Path.GetFileName(endpoint) + " tidak tersedia, cek kandidat berikutnya.");
                        continue;
                    }

                    try
                    {
                        AppendLog(type + ": Authorization Bearer belum berhasil untuk " + Path.GetFileName(endpoint) + ", mencoba query token.");
                        var dapodikBody = HttpGet(fallbackEndpoint, null);
                        records = ExtractRecords(dapodikBody);
                        endpoint = fallbackEndpoint;
                        authLabel = "query token";
                        found = true;
                        break;
                    }
                    catch (Exception queryEx)
                    {
                        errors.Add(Path.GetFileName(endpoint) + " Bearer: " + bearerEx.Message + " Query: " + queryEx.Message);
                        if (IsOptionalEndpointMissingResponse(type, queryEx.Message))
                        {
                            AppendLog(type + ": endpoint " + Path.GetFileName(endpoint) + " tidak tersedia, cek kandidat berikutnya.");
                        }
                    }
                }
            }

            if (!found)
            {
                throw new InvalidOperationException("Dapodik menolak semua endpoint kandidat. " + string.Join("; ", errors.ToArray()));
            }
            AppendLog(type + ": data Dapodik terbaca via " + authLabel + ".");

            var payload = new Dictionary<string, object>
            {
                { "type", type },
                { "npsn", npsn },
                { "token", bridgeToken },
                { "data", records }
            };
            var jsonPayload = serializer.Serialize(payload);

            AppendLog(type + ": mengirim ke bridge...");
            var bridgeResponse = HttpPostJson(bridgeUrl, jsonPayload, bridgeToken);
            AppendLog(type + ": " + bridgeResponse);
            if (IsBridgeTokenError(bridgeResponse))
            {
                throw new InvalidOperationException("Token sinkron tidak valid. Pastikan Link Web Raport, NPSN, dan Token Web Service Dapodik sama dengan konfigurasi Update Data pada server tujuan. Di mode portable, cukup ubah field lalu klik Simpan Konfigurasi.");
            }
            if (bridgeResponse.StartsWith("HTTP 4", StringComparison.Ordinal) || bridgeResponse.StartsWith("HTTP 5", StringComparison.Ordinal))
            {
                throw new InvalidOperationException("Bridge e-rapor menolak data. Respons: " + bridgeResponse);
            }
        }

        private List<string> BuildDapodikEndpoints(string baseUrl, string type, string npsn, string token)
        {
            string[] endpoints;
            if (!dapodikEndpoints.TryGetValue(type, out endpoints))
            {
                throw new InvalidOperationException("Jenis data tidak valid: " + type);
            }

            var urls = new List<string>();
            foreach (var endpoint in endpoints)
            {
                var url = baseUrl.TrimEnd('/') + "/WebService/" + endpoint
                    + "?npsn=" + Uri.EscapeDataString(npsn);
                if (!string.IsNullOrEmpty(token))
                {
                    url += "&token=" + Uri.EscapeDataString(token);
                }
                urls.Add(url);
            }
            return urls;
        }

        private object ExtractRecords(string json)
        {
            object parsed;
            try
            {
                parsed = serializer.DeserializeObject(json);
            }
            catch (Exception ex)
            {
                throw new InvalidOperationException(
                    "Dapodik tidak mengembalikan JSON. Respons: " + PreviewBody(json)
                    + ". Jika respons diawali 'Access', cek Token Web Service Dapodik, NPSN, dan status Web Service di aplikasi Dapodik.",
                    ex
                );
            }

            var dictionary = parsed as Dictionary<string, object>;
            if (dictionary != null)
            {
                foreach (var key in new[] { "data", "rows", "result" })
                {
                    if (dictionary.ContainsKey(key) && dictionary[key] != null)
                    {
                        return dictionary[key];
                    }
                }
            }

            if (parsed is object[])
            {
                return parsed;
            }

            var list = new ArrayList();
            list.Add(parsed);
            return list;
        }

        private static string PreviewBody(string body)
        {
            body = (body ?? "").Replace("\r", " ").Replace("\n", " ").Trim();
            if (body.Length == 0)
            {
                return "(kosong)";
            }
            return body.Length > 240 ? body.Substring(0, 240) + "..." : body;
        }

        private static bool IsBridgeTokenError(string message)
        {
            var value = message ?? "";
            return value.IndexOf("Token bridge", StringComparison.OrdinalIgnoreCase) >= 0
                || value.IndexOf("Token sinkron", StringComparison.OrdinalIgnoreCase) >= 0;
        }

        private static bool IsOptionalEndpointMissing(string type, string message)
        {
            var value = message ?? "";
            return value.IndexOf("Dapodik menolak semua endpoint kandidat", StringComparison.OrdinalIgnoreCase) >= 0
                && IsOptionalEndpointMissingResponse(type, value);
        }

        private static bool IsOptionalEndpointMissingResponse(string type, string message)
        {
            if (type != "anggota_rombel" && type != "pembelajaran")
            {
                return false;
            }

            var value = message ?? "";
            return value.IndexOf("404", StringComparison.OrdinalIgnoreCase) >= 0
                || value.IndexOf("Not Found", StringComparison.OrdinalIgnoreCase) >= 0;
        }

        private string HttpGet(string url, string bearerToken)
        {
            EnableTls12();
            var request = (HttpWebRequest)WebRequest.Create(url);
            request.Method = "GET";
            request.Timeout = 60000;
            request.ReadWriteTimeout = 60000;
            request.Accept = "application/json";
            request.UserAgent = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36";
            if (!string.IsNullOrEmpty(bearerToken))
            {
                request.Headers["Authorization"] = "Bearer " + bearerToken;
            }

            using (var response = (HttpWebResponse)request.GetResponse())
            using (var stream = response.GetResponseStream())
            using (var reader = new StreamReader(stream ?? Stream.Null, Encoding.UTF8))
            {
                return reader.ReadToEnd();
            }
        }

        private string HttpPostJson(string url, string json, string bridgeToken)
        {
            EnableTls12();
            var bytes = Encoding.UTF8.GetBytes(json);
            var request = (HttpWebRequest)WebRequest.Create(url);
            request.Method = "POST";
            request.Timeout = 120000;
            request.ReadWriteTimeout = 120000;
            request.ContentType = "application/json; charset=utf-8";
            request.Headers["X-Eraport-Token"] = bridgeToken;
            request.ContentLength = bytes.Length;

            using (var requestStream = request.GetRequestStream())
            {
                requestStream.Write(bytes, 0, bytes.Length);
            }

            try
            {
                using (var response = (HttpWebResponse)request.GetResponse())
                using (var stream = response.GetResponseStream())
                using (var reader = new StreamReader(stream ?? Stream.Null, Encoding.UTF8))
                {
                    return "HTTP " + (int)response.StatusCode + " " + reader.ReadToEnd();
                }
            }
            catch (WebException ex)
            {
                var response = ex.Response as HttpWebResponse;
                if (response == null)
                {
                    throw;
                }
                using (var stream = response.GetResponseStream())
                using (var reader = new StreamReader(stream ?? Stream.Null, Encoding.UTF8))
                {
                    return "HTTP " + (int)response.StatusCode + " " + reader.ReadToEnd();
                }
            }
        }

        private static void EnableTls12()
        {
            try
            {
                ServicePointManager.SecurityProtocol = ServicePointManager.SecurityProtocol | (SecurityProtocolType)3072;
            }
            catch
            {
                // Older .NET installations may not expose TLS 1.2, but Windows defaults can still work.
            }
        }

        private Dictionary<string, string> ReadEmbeddedConfig()
        {
            try
            {
                var data = File.ReadAllBytes(Application.ExecutablePath);
                var text = Encoding.UTF8.GetString(data);
                var start = text.LastIndexOf(ConfigMarker, StringComparison.Ordinal);
                if (start < 0)
                {
                    return new Dictionary<string, string>();
                }
                start += ConfigMarker.Length;
                var end = text.IndexOf(ConfigEndMarker, start, StringComparison.Ordinal);
                if (end < 0)
                {
                    return new Dictionary<string, string>();
                }
                var encoded = text.Substring(start, end - start).Trim();
                var json = Encoding.UTF8.GetString(Convert.FromBase64String(encoded));
                var raw = serializer.DeserializeObject(json) as Dictionary<string, object>;
                return ToStringDictionary(raw);
            }
            catch
            {
                return new Dictionary<string, string>();
            }
        }

        private Dictionary<string, string> LoadConfigFile(string path)
        {
            if (!File.Exists(path))
            {
                return new Dictionary<string, string>();
            }

            var result = new Dictionary<string, string>();
            foreach (var line in File.ReadAllLines(path))
            {
                var index = line.IndexOf('=');
                if (index <= 0)
                {
                    continue;
                }
                var key = line.Substring(0, index);
                var value = line.Substring(index + 1);
                try
                {
                    result[key] = Encoding.UTF8.GetString(Convert.FromBase64String(value));
                }
                catch
                {
                    result[key] = value;
                }
            }
            return result;
        }

        private void SaveConfig()
        {
            var values = new Dictionary<string, string>
            {
                { "version", AppVersion },
                { "dapodik_url", GetText(dapodikUrlText) },
                { "dapodik_token", GetText(dapodikTokenText) },
                { "npsn", GetText(npsnText) },
                { "bridge_url", GetText(bridgeUrlText) },
                { "bridge_token", GetText(bridgeTokenText) },
                { "type", SelectedType() }
            };

            var configPath = ActiveConfigPath();
            var dir = Path.GetDirectoryName(configPath);
            if (!Directory.Exists(dir))
            {
                Directory.CreateDirectory(dir);
            }

            var lines = new List<string>();
            foreach (var pair in values)
            {
                lines.Add(pair.Key + "=" + Convert.ToBase64String(Encoding.UTF8.GetBytes(pair.Value ?? "")));
            }
            File.WriteAllLines(configPath, lines.ToArray());
        }

        private static string PortableConfigPath()
        {
            return Path.Combine(AppDomain.CurrentDomain.BaseDirectory, PortableConfigFileName);
        }

        private static string LegacyConfigPath()
        {
            return Path.Combine(
                Environment.GetFolderPath(Environment.SpecialFolder.ApplicationData),
                "EraportDapodikBridgeHelper",
                "config.txt"
            );
        }

        private static string ActiveConfigPath()
        {
            var portablePath = PortableConfigPath();
            if (File.Exists(portablePath) || CanWriteToDirectory(Path.GetDirectoryName(portablePath)))
            {
                return portablePath;
            }

            return LegacyConfigPath();
        }

        private static bool CanWriteToDirectory(string dir)
        {
            try
            {
                if (string.IsNullOrEmpty(dir))
                {
                    return false;
                }
                if (!Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                }

                var testPath = Path.Combine(dir, ".eraport-write-test-" + Guid.NewGuid().ToString("N") + ".tmp");
                File.WriteAllText(testPath, "ok");
                File.Delete(testPath);
                return true;
            }
            catch
            {
                return false;
            }
        }

        private void ApplyConfig(Dictionary<string, string> config, bool preferIncoming)
        {
            ApplyText(dapodikUrlText, config, "dapodik_url", preferIncoming);
            ApplyText(dapodikTokenText, config, "dapodik_token", preferIncoming);
            ApplyText(npsnText, config, "npsn", preferIncoming);
            ApplyText(bridgeUrlText, config, "bridge_url", preferIncoming);
            ApplyText(bridgeTokenText, config, "bridge_token", preferIncoming);
            string type;
            if (config.TryGetValue("type", out type) && (preferIncoming || SelectedType().Length == 0))
            {
                SelectType(type);
            }
        }

        private static void ApplyText(TextBox textBox, Dictionary<string, string> config, string key, bool preferIncoming)
        {
            string value;
            if (config.TryGetValue(key, out value) && (preferIncoming || textBox.Text.Trim().Length == 0))
            {
                textBox.Text = value;
            }
        }

        private Dictionary<string, string> ToStringDictionary(Dictionary<string, object> raw)
        {
            var result = new Dictionary<string, string>();
            if (raw == null)
            {
                return result;
            }
            foreach (var pair in raw)
            {
                result[pair.Key] = pair.Value == null ? "" : Convert.ToString(pair.Value);
            }
            return result;
        }

        private string SelectedType()
        {
            var option = typeCombo.InvokeRequired
                ? (TypeOption)Invoke(new Func<TypeOption>(() => (TypeOption)typeCombo.SelectedItem))
                : (TypeOption)typeCombo.SelectedItem;
            return option == null ? "all" : option.Value;
        }

        private void SelectType(string value)
        {
            foreach (var item in typeCombo.Items)
            {
                var option = item as TypeOption;
                if (option != null && option.Value == value)
                {
                    typeCombo.SelectedItem = item;
                    return;
                }
            }
            typeCombo.SelectedIndex = 0;
        }

        private static string NormalizeUrl(string value)
        {
            value = (value ?? "").Trim();
            if (value.Length == 0)
            {
                return "";
            }
            Uri uri;
            if (!Uri.TryCreate(value, UriKind.Absolute, out uri) || (uri.Scheme != "http" && uri.Scheme != "https"))
            {
                throw new InvalidOperationException("URL tidak valid: " + value);
            }
            return value.TrimEnd('/');
        }

        private static string NormalizeBridgeUrl(string value)
        {
            var url = NormalizeUrl(value);
            if (url.Length == 0)
            {
                return "";
            }

            Uri uri;
            if (!Uri.TryCreate(url, UriKind.Absolute, out uri))
            {
                throw new InvalidOperationException("URL tidak valid: " + value);
            }

            if (uri.AbsolutePath.EndsWith(".php", StringComparison.OrdinalIgnoreCase))
            {
                return url;
            }

            return url.TrimEnd('/') + "/dapodik_bridge.php";
        }

        private string GetText(TextBox textBox)
        {
            if (textBox.InvokeRequired)
            {
                return (string)Invoke(new Func<string>(() => textBox.Text.Trim()));
            }
            return textBox.Text.Trim();
        }

        private void AppendLog(string message)
        {
            if (logText.InvokeRequired)
            {
                BeginInvoke(new Action<string>(AppendLog), message);
                return;
            }
            logText.AppendText("[" + DateTime.Now.ToString("HH:mm:ss") + "] " + message + Environment.NewLine);
        }

        private void SetBusy(bool busy)
        {
            if (InvokeRequired)
            {
                BeginInvoke(new Action<bool>(SetBusy), busy);
                return;
            }
            syncAllButton.Enabled = !busy;
            syncSelectedButton.Enabled = !busy;
            saveButton.Enabled = !busy;
        }

        private sealed class TypeOption
        {
            public string Value { get; private set; }
            private string Label { get; set; }

            public TypeOption(string value, string label)
            {
                Value = value;
                Label = label;
            }

            public override string ToString()
            {
                return Label;
            }
        }
    }
}
