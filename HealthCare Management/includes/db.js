// Simulated database functions - Replace these with actual database calls later
const mockPatientData = {
    profile: {
        name: 'John Doe',  // Add some initial mock data
        email: 'john.doe@example.com',
        age: '30',
        gender: 'Male',
        maritalStatus: 'Single',
        bio: 'Patient profile not yet completed.',
        condition: 'No conditions recorded',
        address: 'Address not provided',
        emergencyContact: {
            name: 'Not provided',
            phone: 'Not provided',
            relationship: 'Not specified'
        },
        profilePicture: 'Pictures/Logo.jpg'
    },
    appointments: {
        upcoming: [
            {
                id: '1',
                datetime: '2024-03-25T10:00:00',
                doctor: 'Dr. Smith',
                type: 'General Checkup',
                status: 'Scheduled',
                location: 'Main Clinic',
                notes: 'Regular checkup appointment'
            }
        ],
        history: [
            {
                id: '2',
                datetime: '2024-02-20T15:30:00',
                doctor: 'Dr. Johnson',
                type: 'Follow-up',
                status: 'Completed',
                location: 'Main Clinic',
                notes: 'Follow-up appointment completed'
            }
        ]
    },
    notifications: [
        {
            id: '1',
            type: 'appointment',
            title: 'Upcoming Appointment',
            content: 'You have an appointment scheduled with Dr. Smith',
            datetime: new Date(Date.now() - 3600000).toISOString(), // 1 hour ago
            requiresReply: false
        },
        {
            id: '2',
            type: 'message',
            title: 'Message from Dr. Johnson',
            content: 'Please remember to bring your previous test results.',
            datetime: new Date(Date.now() - 7200000).toISOString(), // 2 hours ago
            requiresReply: true
        }
    ]
};

// Profile functions
async function fetchPatientProfile() {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    return mockPatientData.profile;
}

async function updatePatientProfile(profileData) {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    mockPatientData.profile = {...mockPatientData.profile, ...profileData};
    return { success: true, data: mockPatientData.profile };
}

async function updateProfilePicture(imageFile) {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    const imageUrl = URL.createObjectURL(imageFile);
    mockPatientData.profile.profilePicture = imageUrl;
    return { success: true, imageUrl };
}

// Appointment functions
async function fetchUpcomingAppointments() {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    return mockPatientData.appointments.upcoming;
}

async function fetchAppointmentHistory() {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    return mockPatientData.appointments.history;
}

// Notifications functions
async function fetchNotifications() {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    return mockPatientData.notifications;
}

async function sendMessageReply(messageId, replyText) {
    // Simulate network delay
    await new Promise(resolve => setTimeout(resolve, 1000));
    
    // Add reply to notifications
    mockPatientData.notifications.unshift({
        id: Date.now().toString(),
        type: 'message',
        title: 'Your Reply',
        content: replyText,
        datetime: new Date().toISOString(),
        requiresReply: false
    });
    
    return { success: true, message: 'Reply sent successfully' };
}

// Helper function to clear all data (for testing)
async function clearAllData() {
    mockPatientData.profile = {
        name: '',
        email: '',
        age: '',
        gender: '',
        maritalStatus: '',
        bio: '',
        condition: '',
        address: '',
        emergencyContact: {
            name: '',
            phone: '',
            relationship: ''
        },
        profilePicture: 'Pictures/Logo.jpg'
    };
    mockPatientData.appointments.upcoming = [];
    mockPatientData.appointments.history = [];
    mockPatientData.notifications = [];
    return { success: true, message: 'All data cleared' };
}

// Example of how to structure the data
const exampleData = {
    profile: {
        name: '',
        email: '',
        age: '',
        gender: '',
        maritalStatus: '',
        bio: '',
        condition: '',
        address: '',
        emergencyContact: {
            name: '',
            phone: '',
            relationship: ''
        },
        profilePicture: ''
    },
    appointments: {
        upcoming: [
            {
                id: '',
                datetime: '',
                doctor: '',
                type: '',
                status: '',
                location: '',
                notes: ''
            }
        ],
        history: [
            {
                id: '',
                datetime: '',
                doctor: '',
                type: '',
                status: '',
                location: '',
                notes: ''
            }
        ]
    },
    notifications: [
        {
            id: '',
            type: '',
            title: '',
            content: '',
            datetime: '',
            requiresReply: false
        }
    ]
}; 