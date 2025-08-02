const Contact = require('../models/Contact');
const Database = require('../config/database');
const XLSX = require('xlsx');
const fs = require('fs');
const path = require('path');

class ContactController {
    constructor() {
        this.db = new Database();
        this.contactModel = new Contact(this.db);
    }

    /**
     * Get all contacts with pagination
     */
    async getContacts(req, res) {
        try {
            const page = parseInt(req.query.page) || 1;
            const perPage = parseInt(req.query.per_page) || 10;
            const search = req.query.search;
            const status = req.query.status;
            const company = req.query.company;

            let conditions = {};
            if (status) conditions.status = status;
            if (company) conditions.company = company;

            let contacts;
            if (search) {
                contacts = await this.contactModel.search(search);
            } else {
                contacts = await this.contactModel.paginate(page, perPage, conditions);
            }

            res.json({
                success: true,
                data: contacts
            });
        } catch (error) {
            console.error('Get contacts error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to get contacts',
                error: error.message
            });
        }
    }

    /**
     * Get single contact
     */
    async getContact(req, res) {
        try {
            const { id } = req.params;
            const contact = await this.contactModel.findById(id);

            if (!contact) {
                return res.status(404).json({
                    success: false,
                    message: 'Contact not found'
                });
            }

            res.json({
                success: true,
                data: { contact }
            });
        } catch (error) {
            console.error('Get contact error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to get contact',
                error: error.message
            });
        }
    }

    /**
     * Create new contact
     */
    async createContact(req, res) {
        try {
            const { first_name, last_name, email, phone, company, position, status, lead_source } = req.body;

            // Validate required fields
            if (!first_name || !last_name || !email) {
                return res.status(400).json({
                    success: false,
                    message: 'First name, last name, and email are required'
                });
            }

            // Check if contact with email already exists
            const existingContact = await this.contactModel.findOne({ email });
            if (existingContact) {
                return res.status(400).json({
                    success: false,
                    message: 'Contact with this email already exists'
                });
            }

            const contactData = {
                first_name,
                last_name,
                email,
                phone: phone || '',
                company: company || '',
                position: position || '',
                status: status || 'new',
                lead_source: lead_source || 'manual'
            };

            const contact = await this.contactModel.create(contactData);

            res.status(201).json({
                success: true,
                message: 'Contact created successfully',
                data: { contact }
            });
        } catch (error) {
            console.error('Create contact error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to create contact',
                error: error.message
            });
        }
    }

    /**
     * Update contact
     */
    async updateContact(req, res) {
        try {
            const { id } = req.params;
            const { first_name, last_name, email, phone, company, position, status, lead_source } = req.body;

            // Check if contact exists
            const existingContact = await this.contactModel.findById(id);
            if (!existingContact) {
                return res.status(404).json({
                    success: false,
                    message: 'Contact not found'
                });
            }

            // Check if email is being changed and if it already exists
            if (email && email !== existingContact.email) {
                const contactWithEmail = await this.contactModel.findOne({ email });
                if (contactWithEmail) {
                    return res.status(400).json({
                        success: false,
                        message: 'Contact with this email already exists'
                    });
                }
            }

            const updateData = {};
            if (first_name) updateData.first_name = first_name;
            if (last_name) updateData.last_name = last_name;
            if (email) updateData.email = email;
            if (phone !== undefined) updateData.phone = phone;
            if (company !== undefined) updateData.company = company;
            if (position !== undefined) updateData.position = position;
            if (status) updateData.status = status;
            if (lead_source) updateData.lead_source = lead_source;

            const updatedContact = await this.contactModel.update(id, updateData);

            res.json({
                success: true,
                message: 'Contact updated successfully',
                data: { contact: updatedContact }
            });
        } catch (error) {
            console.error('Update contact error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to update contact',
                error: error.message
            });
        }
    }

    /**
     * Delete contact
     */
    async deleteContact(req, res) {
        try {
            const { id } = req.params;

            // Check if contact exists
            const contact = await this.contactModel.findById(id);
            if (!contact) {
                return res.status(404).json({
                    success: false,
                    message: 'Contact not found'
                });
            }

            await this.contactModel.delete(id);

            res.json({
                success: true,
                message: 'Contact deleted successfully'
            });
        } catch (error) {
            console.error('Delete contact error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to delete contact',
                error: error.message
            });
        }
    }

    /**
     * Bulk upload contacts from file
     */
    async bulkUpload(req, res) {
        try {
            if (!req.file) {
                return res.status(400).json({
                    success: false,
                    message: 'No file uploaded'
                });
            }

            const filePath = req.file.path;
            const fileExtension = path.extname(req.file.originalname).toLowerCase();

            let contacts = [];

            if (fileExtension === '.xlsx' || fileExtension === '.xls') {
                // Parse Excel file
                const workbook = XLSX.readFile(filePath);
                const sheetName = workbook.SheetNames[0];
                const worksheet = workbook.Sheets[sheetName];
                contacts = XLSX.utils.sheet_to_json(worksheet);
            } else if (fileExtension === '.csv') {
                // Parse CSV file
                const csv = require('csv-parser');
                contacts = await new Promise((resolve, reject) => {
                    const results = [];
                    fs.createReadStream(filePath)
                        .pipe(csv())
                        .on('data', (data) => results.push(data))
                        .on('end', () => resolve(results))
                        .on('error', reject);
                });
            } else {
                return res.status(400).json({
                    success: false,
                    message: 'Unsupported file format. Please upload Excel or CSV file.'
                });
            }

            // Process and validate contacts
            const validContacts = [];
            const errors = [];

            for (let i = 0; i < contacts.length; i++) {
                const contact = contacts[i];
                const rowNumber = i + 2; // +2 because of header row and 0-based index

                // Map column names (case-insensitive)
                const mappedContact = {};
                Object.keys(contact).forEach(key => {
                    const lowerKey = key.toLowerCase();
                    if (lowerKey.includes('first') || lowerKey.includes('name')) {
                        mappedContact.first_name = contact[key];
                    } else if (lowerKey.includes('last')) {
                        mappedContact.last_name = contact[key];
                    } else if (lowerKey.includes('email')) {
                        mappedContact.email = contact[key];
                    } else if (lowerKey.includes('phone')) {
                        mappedContact.phone = contact[key];
                    } else if (lowerKey.includes('company')) {
                        mappedContact.company = contact[key];
                    } else if (lowerKey.includes('position')) {
                        mappedContact.position = contact[key];
                    } else if (lowerKey.includes('status')) {
                        mappedContact.status = contact[key];
                    } else if (lowerKey.includes('lead') || lowerKey.includes('source')) {
                        mappedContact.lead_source = contact[key];
                    }
                });

                // Validate required fields
                if (!mappedContact.first_name || !mappedContact.last_name || !mappedContact.email) {
                    errors.push(`Row ${rowNumber}: Missing required fields (first name, last name, or email)`);
                    continue;
                }

                // Check if email already exists
                const existingContact = await this.contactModel.findOne({ email: mappedContact.email });
                if (existingContact) {
                    errors.push(`Row ${rowNumber}: Email ${mappedContact.email} already exists`);
                    continue;
                }

                // Set defaults
                mappedContact.status = mappedContact.status || 'new';
                mappedContact.lead_source = mappedContact.lead_source || 'import';
                mappedContact.phone = mappedContact.phone || '';
                mappedContact.company = mappedContact.company || '';
                mappedContact.position = mappedContact.position || '';

                validContacts.push(mappedContact);
            }

            // Insert valid contacts
            let insertedCount = 0;
            if (validContacts.length > 0) {
                const result = await this.contactModel.bulkInsert(validContacts);
                insertedCount = result.inserted;
            }

            // Clean up uploaded file
            fs.unlinkSync(filePath);

            res.json({
                success: true,
                message: 'Bulk upload completed',
                data: {
                    total: contacts.length,
                    valid: validContacts.length,
                    inserted: insertedCount,
                    errors: errors
                }
            });
        } catch (error) {
            console.error('Bulk upload error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to process bulk upload',
                error: error.message
            });
        }
    }

    /**
     * Get contact statistics
     */
    async getStats(req, res) {
        try {
            const [totalContacts, statusStats, leadSourceStats, companyStats] = await Promise.all([
                this.contactModel.count(),
                this.contactModel.getStatsByStatus(),
                this.contactModel.getStatsByLeadSource(),
                this.contactModel.getStatsByCompany()
            ]);

            res.json({
                success: true,
                data: {
                    total_contacts: totalContacts,
                    by_status: statusStats,
                    by_lead_source: leadSourceStats,
                    by_company: companyStats
                }
            });
        } catch (error) {
            console.error('Get stats error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to get statistics',
                error: error.message
            });
        }
    }

    /**
     * Export contacts
     */
    async exportContacts(req, res) {
        try {
            const format = req.query.format || 'csv';
            const contacts = await this.contactModel.getForExport();

            if (format === 'csv') {
                const createCsvWriter = require('csv-writer').createObjectCsvWriter;
                const csvWriter = createCsvWriter({
                    path: 'contacts_export.csv',
                    header: [
                        { id: 'first_name', title: 'First Name' },
                        { id: 'last_name', title: 'Last Name' },
                        { id: 'email', title: 'Email' },
                        { id: 'phone', title: 'Phone' },
                        { id: 'company', title: 'Company' },
                        { id: 'position', title: 'Position' },
                        { id: 'status', title: 'Status' },
                        { id: 'lead_source', title: 'Lead Source' },
                        { id: 'created_at', title: 'Created At' }
                    ]
                });

                await csvWriter.writeRecords(contacts);

                res.download('contacts_export.csv', 'contacts_export.csv', (err) => {
                    if (err) {
                        console.error('Download error:', err);
                    }
                    // Clean up file after download
                    fs.unlinkSync('contacts_export.csv');
                });
            } else {
                res.status(400).json({
                    success: false,
                    message: 'Unsupported export format'
                });
            }
        } catch (error) {
            console.error('Export contacts error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to export contacts',
                error: error.message
            });
        }
    }

    /**
     * Delete all contacts
     */
    async deleteAllContacts(req, res) {
        try {
            const result = await this.contactModel.deleteAll();

            res.json({
                success: true,
                message: 'All contacts deleted successfully',
                data: { deleted: result.deleted }
            });
        } catch (error) {
            console.error('Delete all contacts error:', error);
            res.status(500).json({
                success: false,
                message: 'Failed to delete all contacts',
                error: error.message
            });
        }
    }
}

module.exports = ContactController; 