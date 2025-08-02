import React from 'react';
import { Link, useLocation } from 'react-router-dom';

const Sidebar: React.FC = () => {
  const location = useLocation();

  const menuItems = [
    {
      name: 'Dashboard',
      path: '/dashboard',
      icon: '📊'
    },
    {
      name: 'Contacts',
      path: '/contacts',
      icon: '👥'
    },
    {
      name: 'Campaigns',
      path: '/campaigns',
      icon: '📧'
    },
    {
      name: 'Employee Management',
      path: '/employees',
      icon: '👨‍💼'
    },
    {
      name: 'Settings',
      path: '/settings',
      icon: '⚙️'
    }
  ];

  return (
    <div className="sidebar">
      <div className="p-6">
        <h1 className="text-2xl font-bold text-white">ACRM</h1>
        <p className="text-gray-400 text-sm">Admin Panel</p>
      </div>
      
      <nav className="mt-8">
        <ul className="space-y-2">
          {menuItems.map((item) => (
            <li key={item.path}>
              <Link
                to={item.path}
                className={`flex items-center px-6 py-3 text-gray-300 hover:bg-gray-700 hover:text-white transition-colors duration-200 ${
                  location.pathname === item.path ? 'bg-gray-700 text-white' : ''
                }`}
              >
                <span className="mr-3">{item.icon}</span>
                {item.name}
              </Link>
            </li>
          ))}
        </ul>
      </nav>
      
      <div className="absolute bottom-0 w-full p-6">
        <div className="flex items-center space-x-3">
          <div className="w-8 h-8 bg-gray-600 rounded-full flex items-center justify-center">
            <span className="text-white text-sm">A</span>
          </div>
          <div>
            <p className="text-white text-sm font-medium">Admin User</p>
            <p className="text-gray-400 text-xs">admin@acrm.com</p>
          </div>
        </div>
      </div>
    </div>
  );
};

export default Sidebar; 