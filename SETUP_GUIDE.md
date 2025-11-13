# Two-Computer Server Setup Guide for FunaGig

This guide provides a comprehensive, step-by-step process for setting up the FunaGig application on two separate Windows 10 computers. One computer will serve the frontend using a simple Python server, and the other will serve the backend using an XAMPP (Apache/MySQL) stack. Both computers will be connected over a single mobile hotspot.

---

### **Step 1: Prerequisites and Network Setup**

This initial step ensures both your computers are ready and can communicate with each other over the local network.

#### **Part A: Software Installation**

**On the Backend Computer:**

1.  **Install XAMPP:**
    *   Visit the official Apache Friends website at [https://www.apachefriends.org](https://www.apachefriends.org) and download the latest version of XAMPP for Windows.
    *   Run the installer. You can proceed with the default components, but for this setup, you primarily need **Apache** and **MySQL**.

**On the Frontend Computer:**

1.  **Install Python:**
    *   Go to the official Python website at [https://www.python.org/downloads/](https://www.python.org/downloads/) and download the latest version for Windows.
    *   Launch the installer. **Crucially, on the first setup screen, check the box at the bottom that says "Add Python to PATH."** This will allow you to run the Python server from any folder.

#### **Part B: Network Connection and Finding IP Addresses**

1.  **Connect to the Hotspot:**
    *   Turn on the mobile hotspot on your phone.
    *   Connect both the frontend and backend computers to this Wi-Fi network.

2.  **Find the Local IP Address for Each Computer:**
    *   On **each** computer, you need to find its unique local IP address.
    *   Press the **Windows Key + R** to open the Run dialog.
    *   Type `cmd` and press **Enter** to open the Command Prompt.
    *   In the Command Prompt, type the following command and press **Enter**:
        ```bash
        ipconfig
        ```
    *   Look for the section named **"Wireless LAN adapter Wi-Fi"** and find the **"IPv4 Address"**. It will typically look like `192.168.x.x`.
    *   **Write down the IP address for both the backend and frontend computers.** You will need them for the configuration steps that follow.

---

### **Step 2: Backend Computer Configuration (XAMPP Server)**

This step will get your backend up and running, with the database and firewall configured to allow the frontend to connect.

#### **Part A: Project File Setup**

1.  **Locate the `htdocs` Folder:**
    *   Open File Explorer and navigate to the directory where you installed XAMPP (by default, it is `C:\xampp`).
    *   Inside this folder, you will find a subfolder named `htdocs`. This is the web root for your Apache server.

2.  **Copy Your Project Files:**
    *   Copy the entire FunaGig project folder into the `htdocs` directory. For simplicity, you can rename the project folder to something like `funagig`. The final path should look like `C:\xampp\htdocs\funagig`.

#### **Part B: Start Apache and MySQL**

1.  **Launch the XAMPP Control Panel:**
    *   From the main XAMPP folder, run `xampp-control.exe`.
    *   On the control panel, you will see a list of modules.

2.  **Start the Services:**
    *   Click the **Start** button next to **Apache**. It should turn green to indicate it's running.
    *   Click the **Start** button next to **MySQL**. It should also turn green.

#### **Part C: Database Setup**

1.  **Open phpMyAdmin:**
    *   In the XAMPP Control Panel, on the MySQL line, click the **Admin** button. This will open the phpMyAdmin interface in your web browser.

2.  **Create the Database:**
    *   On the left sidebar, click **New**.
    *   For the database name, enter `funagig` and click **Create**.

3.  **Import the Database Schema:**
    *   Select the `funagig` database from the left sidebar.
    *   At the top of the screen, click the **Import** tab.
    *   Click on **"Choose File"** and navigate to your project folder inside `htdocs`. Find the `database/database_unified.sql` file and select it.
    *   Scroll down and click **Import**. This will create all the necessary tables.

4.  **Create the Database User:**
    *   Go back to the phpMyAdmin home page by clicking the logo in the top-left.
    *   Click on the **User accounts** tab.
    *   Click on **Add user account**.
    *   Fill out the form as follows:
        *   **User name:** `funagig_user`
        *   **Host name:** Select `Any host (%)` from the dropdown. This is important as it allows connections from other computers on the network.
        *   **Password:** `funagig_password`
        *   **Re-type:** `funagig_password`
    *   Scroll down to the **Global privileges** section and click **Check all**.
    *   Click the **Go** button at the bottom right to create the user.

#### **Part D: Configure Windows Defender Firewall**

To ensure the frontend computer can reach the backend, you need to allow Apache through the firewall.

1.  **Open Firewall Settings:**
    *   Press the **Windows Key** and type `Windows Defender Firewall`. Select it from the results.

2.  **Allow an App Through Firewall:**
    *   On the left, click **"Allow an app or feature through Windows Defender Firewall."**

3.  **Change Settings:**
    *   You may need to click the **"Change settings"** button first (it requires administrator privileges).

4.  **Find and Allow Apache:**
    *   Scroll down the list to find **"Apache HTTP Server."**
    *   Make sure the checkbox to the left of it is checked.
    *   Also, ensure that the checkbox under the **"Private"** column is checked. The "Public" column can remain unchecked for better security.
    *   Click **OK**.

---

### **Step 3: Frontend Computer Configuration (Python Server)**

Now, we will configure the frontend computer to serve the static files and point to the backend server for all API requests.

#### **Part A: Project File Setup**

1.  **Create a Project Folder:**
    *   On your frontend computer, create a new folder in a convenient location (e.g., on your Desktop or in your Documents) and name it something like `funagig-frontend`.

2.  **Copy Frontend Files:**
    *   Copy all the frontend-related files and folders from the FunaGig project into this new folder. This includes all HTML, CSS, JavaScript files, and any asset folders (like `images` or `css`). **Do not copy the `php` or `database` folders.**

#### **Part B: Update the API Endpoint**

This is the most critical step, as it tells the frontend where to find the backend.

1.  **Open the JavaScript Configuration File:**
    *   Inside your `funagig-frontend` folder, navigate to the `js` subfolder and open the `app.js` file with a text editor (like Notepad, VS Code, or Sublime Text).

2.  **Modify the API URL:**
    *   Near the top of the file, you will find a line that defines the base URL for the API. It likely looks like this:
        ```javascript
        const API_BASE_URL = '/php/api.php';
        ```
    *   You must change this relative path to the full network path of your backend server. Replace the existing line with the following, substituting `<BACKEND_IP_ADDRESS>` with the actual local IP address of your backend computer that you wrote down earlier:
        ```javascript
        const API_BASE_URL = 'http://<BACKEND_IP_ADDRESS>/funagig/php/api.php';
        ```
        *   For example, if your backend computer's IP is `192.168.43.10`, the line should look like this:
            ```javascript
            const API_BASE_URL = 'http://192.168.43.10/funagig/php/api.php';
            ```
    *   Save the `app.js` file.

#### **Part C: Start the Python Web Server**

1.  **Open Command Prompt in the Project Folder:**
    *   Navigate to your `funagig-frontend` folder in File Explorer.
    *   Click on the address bar at the top, type `cmd`, and press **Enter**. This will open a Command Prompt window already in the correct directory.

2.  **Run the Server Command:**
    *   In the Command Prompt, type the following command and press **Enter**:
        ```bash
        python -m http.server 5000
        ```
    *   You should see a message like `Serving HTTP on 0.0.0.0 port 5000 (http://0.0.0.0:5000/) ...`. This means your server is running. **Keep this Command Prompt window open.** Closing it will stop the server.

#### **Part D: Configure Windows Defender Firewall**

Just like with the backend, you need to allow incoming connections to your Python server.

1.  **Open Firewall Settings:**
    *   Press the **Windows Key**, type `Windows Defender Firewall with Advanced Security`, and open it.

2.  **Create a New Inbound Rule:**
    *   On the left, click **"Inbound Rules."**
    *   On the right, click **"New Rule..."**

3.  **Configure the Rule:**
    *   **Rule Type:** Select **Port** and click **Next**.
    *   **Protocol and Ports:** Select **TCP**. For **Specific local ports**, enter `5000` and click **Next**.
    *   **Action:** Select **Allow the connection** and click **Next**.
    *   **Profile:** Keep **Domain**, **Private**, and **Public** checked and click **Next**.
    *   **Name:** Give the rule a descriptive name, like `Python Port 5000`, and click **Finish**.

---

### **Step 4: Final Verification and Testing**

This last step will confirm that the frontend and backend servers are communicating properly across the network.

#### **Part A: Access the Web Application**

1.  **Open a Web Browser:**
    *   On the **frontend computer** (or any other device connected to the same mobile hotspot, including your phone), open a web browser like Chrome or Firefox.

2.  **Navigate to the Frontend Server:**
    *   In the address bar, type `http://<FRONTEND_IP_ADDRESS>:5000` and press **Enter**.
    *   Replace `<FRONTEND_IP_ADDRESS>` with the local IP address of the frontend computer that you wrote down earlier.
    *   For example, if the frontend IP is `192.168.43.50`, you would enter `http://192.168.43.50:5000`.

3.  **Check the Application:**
    *   The FunaGig web application should load in your browser.

#### **Part B: Verify API Communication**

This is how you can be certain that the frontend is successfully fetching data from the backend.

1.  **Open Developer Tools:**
    *   In your browser, press **F12** to open the Developer Tools. You can also right-click on the page and select **"Inspect."**

2.  **Go to the Network Tab:**
    *   In the Developer Tools panel, click on the **"Network"** tab.

3.  **Interact with the Application:**
    *   Use the website as intended. For example, try to log in, view gigs, or perform any action that requires fetching data from the database.

4.  **Monitor the Network Requests:**
    *   As you interact with the site, you will see new entries appear in the Network tab.
    *   Look for requests made to a URL starting with your backend IP address. For example, you should see a request to `http://192.168.43.10/funagig/php/api.php`.
    *   Check the **Status** column for these requests. A status code of **200 OK** indicates a successful request.
    *   If you see status codes in the 400s (like 404 Not Found) or 500s (Internal Server Error), it means there is an issue with the backend connection.

If the website loads and you can see successful API requests with a 200 status code in the Network tab, your two-computer server setup is complete and working correctly.
