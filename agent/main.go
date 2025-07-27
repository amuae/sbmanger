package main

import (
	"bytes"
	"encoding/json"
	"fmt"
	"io/ioutil"
	"log"
	"net"
	"net/http"
	"os"
	"os/exec"
	"runtime"
	"time"

	"github.com/shirou/gopsutil/v3/cpu"
	"github.com/shirou/gopsutil/v3/disk"
	"github.com/shirou/gopsutil/v3/mem"
	gopsnet "github.com/shirou/gopsutil/v3/net"
)

// Config 配置结构
type Config struct {
	MasterIP   string `json:"master_ip"`
	MasterPort int    `json:"master_port"`
	AgentPort  int    `json:"agent_port"`
	Token      string `json:"token"`
	Interval   int    `json:"interval"`
}

// SystemInfo 系统信息
type SystemInfo struct {
	Hostname  string      `json:"hostname"`
	IPAddress string      `json:"ip_address"`
	Timestamp time.Time   `json:"timestamp"`
	CPU       CPUInfo     `json:"cpu"`
	Memory    MemoryInfo  `json:"memory"`
	Disk      DiskInfo    `json:"disk"`
	Network   NetworkInfo `json:"network"`
}

type CPUInfo struct {
	UsagePercent float64 `json:"usage_percent"`
	Cores        int     `json:"cores"`
}

type MemoryInfo struct {
	Total       uint64  `json:"total"`
	Used        uint64  `json:"used"`
	Free        uint64  `json:"free"`
	UsedPercent float64 `json:"used_percent"`
}

type DiskInfo struct {
	Total       uint64  `json:"total"`
	Used        uint64  `json:"used"`
	Free        uint64  `json:"free"`
	UsedPercent float64 `json:"used_percent"`
}

type NetworkInfo struct {
	BytesSent uint64 `json:"bytes_sent"`
	BytesRecv uint64 `json:"bytes_recv"`
}

type ConfigUpdate struct {
	Config string `json:"config"`
}

var (
	configFile = "/opt/sbagent/agent-config.json"
	config     Config
	lastNetIO  *gopsnet.IOCountersStat
)

func main() {
	if len(os.Args) > 1 {
		switch os.Args[1] {
		case "config":
			createDefaultConfig()
			return
		case "install":
			installService()
			return
		case "uninstall":
			uninstallService()
			return
		case "help":
			showHelp()
			return
		}
	}

	if err := loadConfig(); err != nil {
		log.Fatalf("加载配置失败: %v", err)
	}

	hostname, _ := os.Hostname()
	ip := getOutboundIP()
	log.Printf("Agent启动成功 - 主机名: %s, IP: %s", hostname, ip)

	// 启动HTTP服务器
	go startHTTPServer()

	// 首次上报
	reportToMaster()

	// 定时上报
	ticker := time.NewTicker(time.Duration(config.Interval) * time.Second)
	defer ticker.Stop()

	for {
		select {
		case <-ticker.C:
			reportToMaster()
		}
	}
}

func loadConfig() error {
	if _, err := os.Stat(configFile); os.IsNotExist(err) {
		return fmt.Errorf("配置文件 %s 不存在，请运行 '%s config' 创建", configFile, os.Args[0])
	}

	data, err := ioutil.ReadFile(configFile)
	if err != nil {
		return err
	}

	if err := json.Unmarshal(data, &config); err != nil {
		return err
	}

	if config.Interval == 0 {
		config.Interval = 30
	}
	if config.AgentPort == 0 {
		config.AgentPort = 8080
	}
	if config.MasterPort == 0 {
		config.MasterPort = 8081
	}

	return nil
}

func createDefaultConfig() {
	defaultConfig := Config{
		MasterIP:   "127.0.0.1",
		MasterPort: 8081,
		AgentPort:  8080,
		Token:      "your-secret-token",
		Interval:   30,
	}

	// 创建目录
	os.MkdirAll("/opt/sbagent", 0755)

	data, _ := json.MarshalIndent(defaultConfig, "", "  ")
	if err := ioutil.WriteFile(configFile, data, 0644); err != nil {
		log.Fatalf("创建配置文件失败: %v", err)
	}

	fmt.Printf("已创建默认配置文件: %s\n", configFile)
	fmt.Println("请编辑配置文件并设置正确的主控IP和Token")
}

func startHTTPServer() {
	http.HandleFunc("/health", healthHandler)
	http.HandleFunc("/info", infoHandler)
	http.HandleFunc("/config", configHandler)

	addr := fmt.Sprintf(":%d", config.AgentPort)
	log.Printf("Agent HTTP服务器启动在端口 %d", config.AgentPort)
	log.Fatal(http.ListenAndServe(addr, nil))
}

func healthHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "GET" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"status": "ok",
		"time":   time.Now().Format(time.RFC3339),
	})
}

func infoHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "GET" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	token := r.Header.Get("X-Token")
	if token != config.Token {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	info, err := getSystemInfo()
	if err != nil {
		http.Error(w, err.Error(), http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(info)
}

func configHandler(w http.ResponseWriter, r *http.Request) {
	if r.Method != "POST" {
		http.Error(w, "Method not allowed", http.StatusMethodNotAllowed)
		return
	}

	token := r.Header.Get("X-Token")
	if token != config.Token {
		http.Error(w, "Unauthorized", http.StatusUnauthorized)
		return
	}

	var update ConfigUpdate
	if err := json.NewDecoder(r.Body).Decode(&update); err != nil {
		http.Error(w, "Invalid JSON", http.StatusBadRequest)
		return
	}

	// 创建sing-box配置目录
	os.MkdirAll("/opt/sbmanger", 0755)

	// 写入sing-box配置
	if err := ioutil.WriteFile("/opt/sbmanger/config.json", []byte(update.Config), 0644); err != nil {
		http.Error(w, "Failed to write config", http.StatusInternalServerError)
		return
	}

	// 重启sing-box
	cmd := exec.Command("systemctl", "restart", "sing-box")
	if err := cmd.Run(); err != nil {
		http.Error(w, "Failed to restart sing-box", http.StatusInternalServerError)
		return
	}

	w.Header().Set("Content-Type", "application/json")
	json.NewEncoder(w).Encode(map[string]string{
		"status":  "success",
		"message": "配置已更新并重启sing-box",
	})
}

func getSystemInfo() (*SystemInfo, error) {
	hostname, _ := os.Hostname()
	ip := getOutboundIP()

	cpuPercent, err := cpu.Percent(0, false)
	if err != nil {
		return nil, fmt.Errorf("获取CPU使用率失败: %v", err)
	}

	memInfo, err := mem.VirtualMemory()
	if err != nil {
		return nil, fmt.Errorf("获取内存信息失败: %v", err)
	}

	diskInfo, err := disk.Usage("/")
	if err != nil {
		return nil, fmt.Errorf("获取磁盘信息失败: %v", err)
	}

	netIO, err := gopsnet.IOCounters(false)
	if err != nil {
		return nil, fmt.Errorf("获取网络信息失败: %v", err)
	}

	var netStats NetworkInfo
	if len(netIO) > 0 {
		current := &netIO[0]
		if lastNetIO != nil {
			netStats.BytesSent = current.BytesSent - lastNetIO.BytesSent
			netStats.BytesRecv = current.BytesRecv - lastNetIO.BytesRecv
		}
		lastNetIO = current
	}

	cores, _ := cpu.Counts(true)

	return &SystemInfo{
		Hostname:  hostname,
		IPAddress: ip,
		Timestamp: time.Now(),
		CPU: CPUInfo{
			UsagePercent: cpuPercent[0],
			Cores:        cores,
		},
		Memory: MemoryInfo{
			Total:       memInfo.Total,
			Used:        memInfo.Used,
			Free:        memInfo.Free,
			UsedPercent: memInfo.UsedPercent,
		},
		Disk: DiskInfo{
			Total:       diskInfo.Total,
			Used:        diskInfo.Used,
			Free:        diskInfo.Free,
			UsedPercent: diskInfo.UsedPercent,
		},
		Network: netStats,
	}, nil
}

func getOutboundIP() string {
	conn, err := net.Dial("udp", "8.8.8.8:80")
	if err != nil {
		return "127.0.0.1"
	}
	defer conn.Close()

	localAddr := conn.LocalAddr().(*net.UDPAddr)
	return localAddr.IP.String()
}

func reportToMaster() {
	info, err := getSystemInfo()
	if err != nil {
		log.Printf("获取系统信息失败: %v", err)
		return
	}

	jsonData, err := json.Marshal(info)
	if err != nil {
		log.Printf("序列化数据失败: %v", err)
		return
	}

	url := fmt.Sprintf("http://%s:%d/api/report", config.MasterIP, config.MasterPort)
	client := &http.Client{Timeout: 10 * time.Second}

	req, err := http.NewRequest("POST", url, bytes.NewBuffer(jsonData))
	if err != nil {
		log.Printf("创建请求失败: %v", err)
		return
	}

	req.Header.Set("Content-Type", "application/json")
	req.Header.Set("X-Token", config.Token)

	resp, err := client.Do(req)
	if err != nil {
		log.Printf("上报数据失败: %v", err)
		return
	}
	defer resp.Body.Close()

	if resp.StatusCode != 200 {
		body, _ := ioutil.ReadAll(resp.Body)
		log.Printf("主控返回错误: %d - %s", resp.StatusCode, string(body))
		return
	}

	log.Printf("数据上报成功 - CPU: %.2f%%, 内存: %.2f%%", info.CPU.UsagePercent, info.Memory.UsedPercent)
}

func installService() {
	switch runtime.GOOS {
	case "linux":
		installLinuxService()
	case "windows":
		installWindowsService()
	default:
		fmt.Println("不支持的操作系统")
	}
}

func uninstallService() {
	switch runtime.GOOS {
	case "linux":
		uninstallLinuxService()
	case "windows":
		fmt.Println("Windows服务卸载请参考README.md")
	default:
		fmt.Println("不支持的操作系统")
	}
}

func installLinuxService() {
	// 创建Agent目录
	os.MkdirAll("/opt/sbagent", 0755)

	serviceContent := `[Unit]
Description=SBManager Agent
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/sbagent
ExecStart=/opt/sbagent/agent
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
`

	err := ioutil.WriteFile("/etc/systemd/system/sbmanger-agent.service", []byte(serviceContent), 0644)
	if err != nil {
		log.Fatalf("创建服务文件失败: %v", err)
	}

	fmt.Println("服务已创建，请执行以下命令启动:")
	fmt.Println("sudo systemctl daemon-reload")
	fmt.Println("sudo systemctl enable sbmanger-agent")
	fmt.Println("sudo systemctl start sbmanger-agent")
	fmt.Println("sudo systemctl status sbmanger-agent")
}

func uninstallLinuxService() {
	exec.Command("systemctl", "stop", "sbmanger-agent").Run()
	exec.Command("systemctl", "disable", "sbmanger-agent").Run()
	os.Remove("/etc/systemd/system/sbmanger-agent.service")
	exec.Command("systemctl", "daemon-reload").Run()
	fmt.Println("服务已卸载")
}

func installWindowsService() {
	fmt.Println("Windows服务安装请参考README.md")
}

func showHelp() {
	fmt.Printf(`SBManager Agent - Sing-box管理Agent

使用方法:
  %s           运行Agent
  %s config    创建默认配置文件
  %s install   安装为系统服务
  %s uninstall 卸载系统服务
  %s help      显示帮助信息

配置文件: %s
Agent目录: /opt/sbagent
主控目录: /opt/sbmanger
`, os.Args[0], os.Args[0], os.Args[0], os.Args[0], os.Args[0], configFile)
}
