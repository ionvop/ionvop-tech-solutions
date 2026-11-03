# Tech Solution Service Chat

## 1. Project Overview

**Tech Solution Service Chat** is a web-based platform designed to connect local business owners and individuals who need technical solutions with my software development services.

The platform will provide a convenient way for prospective clients to discuss their business challenges, explore possible software or website solutions, and coordinate development projects directly with me.

My services primarily focus on developing custom software applications and websites that address specific business needs, automate repetitive tasks, and streamline existing business operations.

The platform will also serve as a communication hub where clients can monitor project progress, provide additional requirements, submit files, and request updates.

### Technology Stack

The website will be developed using the following technologies:

- **Laravel** – Backend framework and application logic.
- **Blade** – Server-side templating engine.
- **DaisyUI** – UI component library for styling and interface development.
- **Alpine.js** – Lightweight JavaScript framework for interactive frontend functionality.

### Main Pages

The website will primarily consist of three pages:

1. **Landing Page** – Introduces the services, showcases completed projects, and provides ways to get in touch.
2. **Chat Page** – Allows prospective and existing clients to communicate with me and discuss their projects.
3. **Admin Page** – Provides an interface for managing conversations, responding to clients, and monitoring project statuses.

---

## 2. Landing Page

The landing page will serve as the website's main entry point. Its primary purpose is to introduce my services, establish credibility through previous work, and encourage prospective clients to start a conversation.

### 2.1. Hero Section

A prominent introductory section that briefly communicates the purpose of the service and encourages visitors to discuss their business needs.

It may include a call-to-action button that redirects visitors to the chat page.

### 2.2. Services and Pricing

This section will explain the types of services offered, the intended clientele, and the corresponding pricing arrangements.

**Primary Target Market: Local Clients**

The service will primarily cater to individuals and businesses located in:

- Tagum City, Davao del Norte
- Nabunturan, Davao de Oro

Pricing:

- **PHP 2,000** for the initial month of service.
- **PHP 1,000** for every succeeding month of service.

The initial PHP 2,000 payment may be divided into two installments:

- PHP 500 upon commencement of development.
- PHP 1,500 upon delivery of the completed project.

**Secondary Target Market: International Clients**

International clients will also be accommodated, although local clients will receive priority.

Pricing:

- **USD 20** for the initial month of service.
- **USD 10** for every succeeding month of service.

The initial USD 20 payment may be divided into two installments:

- USD 5 upon commencement of development.
- USD 15 upon delivery of the completed project.

**Supported Payment Methods**

- GCash
- PayPal

### 2.3. Portfolio and Completed Projects

The landing page will feature screenshots of three previously completed projects to demonstrate my experience in developing practical, business-oriented software solutions.

**1. Nowcast – AI-Assisted Weather Application**

A weather application that provides current weather information and AI-generated summaries of weather conditions, including activity recommendations based on the user's location and prevailing conditions.

It also allows users to check weather conditions at other locations supported by the Google API.

**2. Inventory Management System – DRMC Nutrition & Dietetics Department**

A custom-built inventory management system developed specifically around the operational workflows and inventory management requirements of the DRMC Nutrition & Dietetics Department.

Rather than relying on a generic inventory tracking approach, the system was designed to accommodate the department's actual processes and requirements.

**3. Attendance Monitoring System – UMTC Accounting Education Department**

An attendance monitoring system designed to simplify attendance recording through the use of barcodes embedded in student identification cards.

The system primarily operates over the local network, reducing its dependence on internet connectivity. This addresses a major limitation of the department's previous attendance monitoring system, which required an internet connection and experienced difficulties in areas with slow or unreliable network access.

Each project will be accompanied by screenshots and a brief description highlighting its purpose, features, and the specific problems it addresses.

### 2.4. Alternative Contact Channels

For visitors who prefer communicating through other platforms, the landing page will also provide links to the following contact channels:

- Email
- Facebook
- Discord

---

## 3. Chat Page

The chat page will serve as the primary communication interface between clients and myself.

It will allow prospective clients to explain their business problems, discuss potential technical solutions, provide project requirements, and request updates on ongoing development projects.

### 3.1. Conversation Management

Each conversation will be assigned a unique identifier generated when the user sends their first message.

The conversation identifier will be stored in the user's browser so they can return to the same conversation without needing to create an account.

This approach will provide a simple and convenient communication experience while minimizing the barriers to contacting my services.

### 3.2. Project Information Sidebar

A sidebar will display relevant information about the client's project and its current status.

It may include:

- **Project Status** – The current stage of the project, such as inquiry, planning, development, or completion.
- **Payment Status** – Information about outstanding payments, completed payments, and payment arrangements.
- **Contract Information** – Relevant contract dates, duration, and current contract status.

This will allow clients to conveniently check important project information without having to ask for updates manually.

### 3.3. Live Chat Interface

The main section of the page will contain the conversation feed, where clients and I can exchange messages.

Planned functionality includes:

- Real-time or near-real-time message updates.
- Sending and viewing images, audio recordings, and video files.
- Sharing geographical locations for coordinating in-person consultations or on-site software deployment.
- Viewing previous messages and uploaded attachments.

### 3.4. AI-Powered Assistant

An AI assistant will be integrated into the chat system to handle initial inquiries and gather relevant information when I am unavailable.

Its primary responsibility will be to understand the client's business problem, collect project requirements, and prepare useful information for me to review.

The assistant may perform the following tasks:

**Client Identification**

- Ask clients for a preferred nickname or display name.
- Use the provided nickname to create a descriptive label for the conversation, making it easier for me to identify and distinguish between clients.

**Contact Information Collection**

- Request additional contact information, such as an email address or social media account.
- Store this information alongside the conversation to provide an alternative way of contacting the client if they lose access to their conversation due to cleared browser data, a changed device, or other circumstances.

**Business Problem Analysis and Requirement Gathering**

- Ask follow-up questions to better understand the client's business operations and challenges.
- Identify the specific problems the client wants to address.
- Gather relevant functional requirements and expectations.
- Clarify details that may affect the scope or complexity of the proposed solution.

**Administrative Summary Generation**

- Generate a structured summary of the conversation for administrative use.
- Organize the identified business problems, proposed solutions, client requirements, and other relevant details into a readable document.
- Make the summary accessible through the admin interface to help me evaluate inquiries and prepare for discussions with prospective clients.

The AI assistant will primarily function as an initial point of contact and information-gathering tool. Project decisions, final requirements, pricing arrangements, and development commitments will remain subject to my review and approval.

---

## 4. Admin Page

The admin page will serve as the management interface for handling client conversations and monitoring ongoing projects.

It will allow me to review incoming inquiries, communicate with clients, and manage relevant project information through a centralized interface.

### 4.1. Authentication

Access to the admin page will require authentication through Google OAuth. Only the Google account matching the email address configured on the server (`ionvop@gmail.com`) will be authorized.

The authorized email address will be managed through server-side configuration rather than being exposed as a frontend-controlled setting.

### 4.2. Conversation Management

The admin interface will display a list of all existing conversations.

Each conversation will provide access to its corresponding message history and client information.

From this interface, I will be able to:

- View and read client messages.
- Send responses through the chat system.
- Review uploaded images, audio, and video files.
- View shared geographical locations.
- Access client-provided contact information.
- Review AI-generated conversation summaries.
- Update project and payment statuses.
- Manage contract dates and other relevant project information.

The admin page will essentially function as a centralized dashboard for handling client communications and managing the progress of commissioned projects.

---

## 5. Overall Project Goal

The primary goal of Tech Solution Service Chat is to establish a simple, accessible, and centralized platform for offering software development services to individuals and businesses.

By combining direct client communication, project information management, portfolio presentation, and AI-assisted inquiry handling, the platform aims to make the process of acquiring and managing software development services more convenient for both clients and myself.

Ultimately, it will serve as both a public-facing service website and a lightweight client management system tailored to my software development workflow.