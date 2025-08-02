const express = require('express');
const router = express.Router();
const { pool } = require('../config/database');

// Get all contacts with search and filtering
router.get('/', async (req, res) => {
  try {
    const { search, date_from, date_to, page = 1, limit = 10 } = req.query;
    const offset = (page - 1) * limit;
    
    let sql = `
      SELECT 
        id,
        first_name,
        last_name,
        email,
        phone,
        company,
        status,
        created_at
      FROM contacts 
      WHERE 1=1
    `;
    
    const params = [];
    
    // Add search condition
    if (search) {
      sql += ` AND (
        CONCAT(first_name, ' ', last_name) LIKE ? OR 
        email LIKE ? OR 
        company LIKE ? OR 
        phone LIKE ?
      )`;
      const searchParam = `%${search}%`;
      params.push(searchParam, searchParam, searchParam, searchParam);
    }
    
    // Add date range conditions
    if (date_from) {
      sql += ` AND DATE(created_at) >= ?`;
      params.push(date_from);
    }
    
    if (date_to) {
      sql += ` AND DATE(created_at) <= ?`;
      params.push(date_to);
    }
    
    // Get total count for pagination
    const countSql = sql.replace(/SELECT.*FROM/, 'SELECT COUNT(*) as total FROM');
    const [countResult] = await pool.execute(countSql, params);
    const totalCount = countResult[0].total;
    
    // Add ordering and pagination
    sql += ` ORDER BY created_at DESC LIMIT ? OFFSET ?`;
    params.push(parseInt(limit), offset);
    
    const [contacts] = await pool.execute(sql, params);
    
    res.json({
      success: true,
      data: contacts,
      pagination: {
        current_page: parseInt(page),
        per_page: parseInt(limit),
        total: totalCount,
        total_pages: Math.ceil(totalCount / limit)
      },
      filters: {
        search,
        date_from,
        date_to
      }
    });
  } catch (error) {
    console.error('Error fetching contacts:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch contacts',
      error: process.env.NODE_ENV === 'development' ? error.message : 'Internal server error'
    });
  }
});

// Get single contact
router.get('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const [contacts] = await pool.execute(
      'SELECT * FROM contacts WHERE id = ?',
      [id]
    );
    
    if (contacts.length === 0) {
      return res.status(404).json({
        success: false,
        message: 'Contact not found'
      });
    }
    
    res.json({
      success: true,
      data: contacts[0]
    });
  } catch (error) {
    console.error('Error fetching contact:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to fetch contact'
    });
  }
});

// Create new contact
router.post('/', async (req, res) => {
  try {
    const { first_name, last_name, email, phone, company } = req.body;
    
    // Validation
    if (!first_name || !last_name || !email) {
      return res.status(400).json({
        success: false,
        message: 'First name, last name, and email are required'
      });
    }
    
    const [result] = await pool.execute(
      'INSERT INTO contacts (first_name, last_name, email, phone, company) VALUES (?, ?, ?, ?, ?)',
      [first_name, last_name, email, phone, company]
    );
    
    const [newContact] = await pool.execute(
      'SELECT * FROM contacts WHERE id = ?',
      [result.insertId]
    );
    
    res.status(201).json({
      success: true,
      message: 'Contact created successfully',
      data: newContact[0]
    });
  } catch (error) {
    console.error('Error creating contact:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to create contact'
    });
  }
});

// Update contact
router.put('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    const { first_name, last_name, email, phone, company, status } = req.body;
    
    const [result] = await pool.execute(
      'UPDATE contacts SET first_name = ?, last_name = ?, email = ?, phone = ?, company = ?, status = ? WHERE id = ?',
      [first_name, last_name, email, phone, company, status, id]
    );
    
    if (result.affectedRows === 0) {
      return res.status(404).json({
        success: false,
        message: 'Contact not found'
      });
    }
    
    const [updatedContact] = await pool.execute(
      'SELECT * FROM contacts WHERE id = ?',
      [id]
    );
    
    res.json({
      success: true,
      message: 'Contact updated successfully',
      data: updatedContact[0]
    });
  } catch (error) {
    console.error('Error updating contact:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to update contact'
    });
  }
});

// Delete contact
router.delete('/:id', async (req, res) => {
  try {
    const { id } = req.params;
    
    const [result] = await pool.execute(
      'DELETE FROM contacts WHERE id = ?',
      [id]
    );
    
    if (result.affectedRows === 0) {
      return res.status(404).json({
        success: false,
        message: 'Contact not found'
      });
    }
    
    res.json({
      success: true,
      message: 'Contact deleted successfully'
    });
  } catch (error) {
    console.error('Error deleting contact:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to delete contact'
    });
  }
});

// Delete all contacts
router.delete('/', async (req, res) => {
  try {
    const [result] = await pool.execute('DELETE FROM contacts');
    
    res.json({
      success: true,
      message: `Successfully deleted ${result.affectedRows} contacts`,
      deleted_count: result.affectedRows
    });
  } catch (error) {
    console.error('Error deleting all contacts:', error);
    res.status(500).json({
      success: false,
      message: 'Failed to delete contacts'
    });
  }
});

module.exports = router; 