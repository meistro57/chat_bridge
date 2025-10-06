const express = require('express');
const fs = require('fs').promises;
const path = require('path');

const app = express();
const PORT = 3000;
const ROLES_FILE = path.join(__dirname, 'roles.json');

// Middleware
app.use(express.json());
app.use(express.static(__dirname));

// Get roles
app.get('/api/roles', async (req, res) => {
    try {
        const data = await fs.readFile(ROLES_FILE, 'utf8');
        res.json(JSON.parse(data));
    } catch (error) {
        console.error('Error reading roles.json:', error);
        res.status(500).json({ error: 'Failed to read roles file' });
    }
});

// Save roles
app.post('/api/roles', async (req, res) => {
    try {
        const rolesData = req.body;

        // Create backup before saving
        const backupFile = path.join(__dirname, `roles.backup.${Date.now()}.json`);
        try {
            const currentData = await fs.readFile(ROLES_FILE, 'utf8');
            await fs.writeFile(backupFile, currentData, 'utf8');
            console.log(`Backup created: ${backupFile}`);
        } catch (backupError) {
            console.warn('Could not create backup:', backupError);
        }

        // Write new data
        await fs.writeFile(ROLES_FILE, JSON.stringify(rolesData, null, 2), 'utf8');
        console.log('Roles saved successfully');

        res.json({ success: true, message: 'Roles saved successfully' });
    } catch (error) {
        console.error('Error saving roles.json:', error);
        res.status(500).json({ error: 'Failed to save roles file' });
    }
});

// Serve the HTML file
app.get('/', (req, res) => {
    res.sendFile(path.join(__dirname, 'roles-editor.html'));
});

app.listen(PORT, () => {
    console.log(`
╔═══════════════════════════════════════════╗
║     🎭 Roles Editor Server Running       ║
╠═══════════════════════════════════════════╣
║  URL: http://localhost:${PORT}              ║
║  Editing: roles.json                      ║
║  Press Ctrl+C to stop                     ║
╚═══════════════════════════════════════════╝
    `);
});
