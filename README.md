# HealthCare-Appointment-System


HealthCare Appointment system
Front-End:
• Homepage with available doctors and booking functionality
• Doctor profile page with schedule and contact option
• User authentication for patients and doctors
• Patient dashboard to view and manage appointments
• Doctor dashboard to update availability and appointments
• Contact page for inquiries
Back-End:
• Database for doctors, patients, and appointments
• API for retrieving doctor schedules and patient bookings
• Authentication with role-based access (patient, doctor, admin)
• CRUD operations for appointment scheduling
• Notification system for reminders
• Admin panel to monitor system usage and disputes
Flow of Webapp
Website Name: DocNow
Logo path: " Pictures\logo.jpg"
Frontend: Html, css & Js (or use framework like tailwind react)
Backend: Using Php, MySql, Xampp
Pages
-Home page
There should be navigation bar. It directs to Home, Doctors, Booking and Contacts. 
Navigation Links
Home – this is where some information about the hospital is presented just put a mockup info in this make this scrollable so that I can put features of the hospital here. The home page is the default page to introduce the hospital
Doctors – Here in this page will show Doctors and if they’re available or not. Once you click a doctor, it will direct in doctor profile page
Booking – Here you can book in any time with available doctor
About – About Us section
Contacts – Contacts Section
-Landing page
•	it should be like the home page exactly the same but for them to use navigations like doctors and etc. they be asked if they want to create account because they can’t use those features if don’t have account so they will then direct to login form. As for the navigation bar add a nav where they can login so add a Log in button in the right edge of the navbar
Account Creation Phase(Signin/Signup)
-Login Page
•	log in page first so this page is different page from the home page. In this page, the user should be able to login, create account or even change password.
*When the user inputs their credentials, the system should retrieved their account details and depending on their roles, they will then directed to different setup of website.
(For example the system detects that the User A is a patient, then he will directed to page for patient users)
-Account Signup
*The user will sign up their names and other information either patient or doctor. As for the admin role, the developer will create a separate account on that.
*In creating account, the user should be able to distinguish its role whether if they’re patient, doctor.
-Forgot password
•	user can change password if they forgot it
So in account registration, a user will choose what role they have. And Upon login, they will then directed to different pages according to their roles
Pages once successfully signed in
-Once signed in, the “Login” button in navbar will change to logout. There will be search icon in that to search user either patient or doctor using names, specialty or cases.
-Patient User page
*In the home section of the Patient user page, the display is the information of the user like name, bio, and other info like appointments/schedule and history of schedules. These informations are editable
*In the navigation links
If they clicked the “Doctors”, they can see the list of available doctors and their specialty, if they clicked a doctor, they can see the profile of the doctor, its schedule and other information. They can also search for their doctor
While in the Booking, they can search also for doctor or specialties, they can book and fill up information regarding the booking. They can book appointments and this will be saved to the database. These appointments will set reminder in the system/device of the user
-Doctor User page
*In the home section of the Doctor user page, the display is the information of the user like name, bio, and other info like appointments/schedule and history of schedules.
This also includes their specialty, profile picture. These informations are editable.
*Doctors can cancel or remove their appointments
In the navigation bar of this page, the only navlinks are home, doctors and logout.
In the doctors section, the user(doctor) can see other doctors information just like for the patient
-Admin Page
-This can only be accessed by a developer using a default credentials.
No other user can create this account directly in account creation only by developer adding it directly in the database.
•	In this page, the admin can monitor the schedule or have a dashboard for all active appointments, they can remove user either patient or doctor. The admin has access to personal informations of all user.
In the Navbar of this page there is Home, Doctors, Patients, Contacts and Logout
In the Home section/default page
It shows graph of active users, transactions/appointments, and etc
In the doctors, they can see list of doctors (scrollable)
In patients, shows a list of patients (scrollable)


Database
Users Table
Here the information of the users specifically their names, emails,  authentications/passwords and etc.

Patients table, 
In this table are those personal information of the user such as bio , age, gender, marital status, condition, address, emergency contact name and number  and their relationship with the patient.


You can do the rest

