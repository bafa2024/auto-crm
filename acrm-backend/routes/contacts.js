const express = require('express');
const router = express.Router();
const ContactController = require('../controllers/ContactController');
const { verifyToken, requireRole } = require('../middleware/auth');
const multer = require('multer');
const path = require('path');

// Configure multer for file uploads
const storage = multer.diskStorage({
    destination: (req, file, cb) => {
        cb(null, process.env.UPLOAD_PATH || './uploads');
    },
    filename: (req, file, cb) => {
        const uniqueSuffix = Date.now() + '-' + Math.round(Math.random() * 1E9);
        cb(null, file.fieldname + '-' + uniqueSuffix + path.extname(file.originalname));
    }
});

const upload = multer({
    storage: storage,
    limits: {
        fileSize: parseInt(process.env.MAX_FILE_SIZE) || 10 * 1024 * 1024 // 10MB default
    },
    fileFilter: (req, file, cb) => {
        const allowedTypes = ['.xlsx', '.xls', '.csv'];
        const ext = path.extname(file.originalname).toLowerCase();
        if (allowedTypes.includes(ext)) {
            cb(null, true);
        } else {
            cb(new Error('Only Excel and CSV files are allowed'));
        }
    }
});

const contactController = new ContactController();

// Apply authentication middleware to all routes
router.use(verifyToken);

// Contact CRUD routes
router.get('/', contactController.getContacts.bind(contactController));
router.get('/stats', contactController.getStats.bind(contactController));
router.get('/export', contactController.exportContacts.bind(contactController));
router.post('/bulk-upload', upload.single('file'), contactController.bulkUpload.bind(contactController));
router.delete('/delete-all', requireRole(['admin']), contactController.deleteAllContacts.bind(contactController));

router.get('/:id', contactController.getContact.bind(contactController));
router.post('/', contactController.createContact.bind(contactController));
router.put('/:id', contactController.updateContact.bind(contactController));
router.delete('/:id', contactController.deleteContact.bind(contactController));

module.exports = router; 