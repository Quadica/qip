import React, { useState, useMemo } from 'react';
import { ChevronRight, ChevronDown, Check, Square, Minus, Layers, Package, Hash, Zap, RefreshCw, Clock } from 'lucide-react';

/*
 * WordPress Admin Color Scheme
 * Matching standard WP admin interface
 */

// Mock data simulating query results from oms_batch_items
// WHERE qty_received < build_qty
const mockBatchItems = [
  { id: 1, sku: 'SOLO-34543', order_id: '284534', build_qty: 25, qty_received: 10, customer: 'Acme Lighting' },
  { id: 2, sku: 'SOLO-78291', order_id: '284534', build_qty: 15, qty_received: 0, customer: 'Acme Lighting' },
  { id: 3, sku: 'SOLO-45182', order_id: '284892', build_qty: 50, qty_received: 25, customer: 'TechCorp Industries' },
  { id: 4, sku: 'CORE-91247', order_id: '283721', build_qty: 100, qty_received: 60, customer: 'MediLight Systems' },
  { id: 5, sku: 'CORE-63958', order_id: '283721', build_qty: 75, qty_received: 50, customer: 'MediLight Systems' },
  { id: 6, sku: 'CORE-27461', order_id: '285103', build_qty: 30, qty_received: 0, customer: 'City Hospital' },
  { id: 7, sku: 'EDGE-58324', order_id: '284256', build_qty: 200, qty_received: 150, customer: 'BuildRight Co' },
  { id: 8, sku: 'EDGE-19847', order_id: '284256', build_qty: 100, qty_received: 75, customer: 'BuildRight Co' },
  { id: 9, sku: 'EDGE-72635', order_id: '285412', build_qty: 40, qty_received: 0, customer: 'Workshop Direct' },
  { id: 10, sku: 'STAR-84219', order_id: '284078', build_qty: 60, qty_received: 30, customer: 'HomeGlow LLC' },
  { id: 11, sku: 'STAR-35762', order_id: '284078', build_qty: 45, qty_received: 20, customer: 'HomeGlow LLC' },
  { id: 12, sku: 'STAR-49183', order_id: '285634', build_qty: 80, qty_received: 40, customer: 'Office Solutions Inc' },
];

// Base type definitions for display
const baseTypeNames = {
  'SOLO': 'Star/O',
  'CORE': 'LUXEON C ES',
  'EDGE': 'LUXEON UV U1',
  'STAR': 'Cree XPG'
};

// WordPress Admin color constants
const colors = {
  // Backgrounds
  bgPrimary: '#f0f0f1',
  bgCard: '#ffffff',
  bgElevated: '#f6f7f7',
  bgHover: '#f0f6fc',
  
  // WordPress blues
  wpBlue: '#2271b1',
  wpBlueHover: '#135e96',
  wpBlueFocus: '#043959',
  wpBlueLight: '#f0f6fc',
  
  // Text
  textPrimary: '#1d2327',
  textSecondary: '#50575e',
  textMuted: '#787c82',
  
  // Accents
  success: '#00a32a',
  successLight: '#edfaef',
  warning: '#dba617',
  warningLight: '#fcf9e8',
  error: '#d63638',
  errorLight: '#fcf0f1',
  
  // Borders
  border: '#c3c4c7',
  borderLight: '#dcdcde',
  borderFocus: '#2271b1',
};

export default function EngravingSelector() {
  const [expandedBaseTypes, setExpandedBaseTypes] = useState(new Set());
  const [expandedOrders, setExpandedOrders] = useState(new Set());
  const [selectedModules, setSelectedModules] = useState(new Set());
  const [activeBaseType, setActiveBaseType] = useState(null);
  const [engraveQuantities, setEngraveQuantities] = useState({});

  // Process data into hierarchical structure
  const hierarchicalData = useMemo(() => {
    const structure = {};
    
    mockBatchItems.forEach(item => {
      const baseType = item.sku.substring(0, 4);
      const qtyNeeded = item.build_qty - item.qty_received;
      
      if (!structure[baseType]) {
        structure[baseType] = {
          name: baseTypeNames[baseType] || baseType,
          orders: {}
        };
      }
      
      if (!structure[baseType].orders[item.order_id]) {
        structure[baseType].orders[item.order_id] = {
          customer: item.customer,
          modules: []
        };
      }
      
      structure[baseType].orders[item.order_id].modules.push({
        id: item.id,
        sku: item.sku,
        qtyNeeded,
        buildQty: item.build_qty,
        qtyReceived: item.qty_received
      });
    });
    
    return structure;
  }, []);

  // Get all module IDs for a base type
  const getBaseTypeModuleIds = (baseType) => {
    const ids = new Set();
    Object.values(hierarchicalData[baseType].orders).forEach(order => {
      order.modules.forEach(m => ids.add(m.id));
    });
    return ids;
  };

  // Get all module IDs for an order within a base type
  const getOrderModuleIds = (baseType, orderId) => {
    const ids = new Set();
    hierarchicalData[baseType].orders[orderId].modules.forEach(m => ids.add(m.id));
    return ids;
  };

  // Check selection state for a base type
  const getBaseTypeSelectionState = (baseType) => {
    const moduleIds = getBaseTypeModuleIds(baseType);
    const selectedCount = [...moduleIds].filter(id => selectedModules.has(id)).length;
    if (selectedCount === 0) return 'none';
    if (selectedCount === moduleIds.size) return 'all';
    return 'partial';
  };

  // Check selection state for an order
  const getOrderSelectionState = (baseType, orderId) => {
    const moduleIds = getOrderModuleIds(baseType, orderId);
    const selectedCount = [...moduleIds].filter(id => selectedModules.has(id)).length;
    if (selectedCount === 0) return 'none';
    if (selectedCount === moduleIds.size) return 'all';
    return 'partial';
  };

  // Toggle base type expansion
  const toggleBaseType = (baseType) => {
    const newExpanded = new Set(expandedBaseTypes);
    if (newExpanded.has(baseType)) {
      newExpanded.delete(baseType);
      setActiveBaseType(null);
    } else {
      newExpanded.add(baseType);
      setActiveBaseType(baseType);
    }
    setExpandedBaseTypes(newExpanded);
  };

  // Toggle order expansion
  const toggleOrder = (orderKey) => {
    const newExpanded = new Set(expandedOrders);
    if (newExpanded.has(orderKey)) {
      newExpanded.delete(orderKey);
    } else {
      newExpanded.add(orderKey);
    }
    setExpandedOrders(newExpanded);
  };

  // Select/deselect all modules in a base type
  const selectBaseType = (baseType) => {
    const moduleIds = getBaseTypeModuleIds(baseType);
    const currentState = getBaseTypeSelectionState(baseType);
    const newSelected = new Set(selectedModules);
    
    if (currentState === 'all') {
      // Deselect all
      moduleIds.forEach(id => newSelected.delete(id));
    } else {
      // Select all
      moduleIds.forEach(id => newSelected.add(id));
    }
    
    setSelectedModules(newSelected);
    
    // Auto-expand when selecting
    if (currentState !== 'all') {
      const newExpandedBase = new Set(expandedBaseTypes);
      newExpandedBase.add(baseType);
      setExpandedBaseTypes(newExpandedBase);
      
      const newExpandedOrders = new Set(expandedOrders);
      Object.keys(hierarchicalData[baseType].orders).forEach(orderId => {
        newExpandedOrders.add(`${baseType}-${orderId}`);
      });
      setExpandedOrders(newExpandedOrders);
    }
  };

  // Toggle order selection
  const toggleOrderSelection = (baseType, orderId) => {
    const moduleIds = getOrderModuleIds(baseType, orderId);
    const currentState = getOrderSelectionState(baseType, orderId);
    const newSelected = new Set(selectedModules);
    
    if (currentState === 'all') {
      moduleIds.forEach(id => newSelected.delete(id));
    } else {
      moduleIds.forEach(id => newSelected.add(id));
    }
    
    setSelectedModules(newSelected);
  };

  // Toggle individual module selection
  const toggleModuleSelection = (moduleId) => {
    const newSelected = new Set(selectedModules);
    if (newSelected.has(moduleId)) {
      newSelected.delete(moduleId);
    } else {
      newSelected.add(moduleId);
    }
    setSelectedModules(newSelected);
  };

  // Get engrave quantity for a module
  const getEngraveQty = (moduleId, defaultQty) => {
    return engraveQuantities[moduleId] !== undefined ? engraveQuantities[moduleId] : defaultQty;
  };

  // Update engrave quantity
  const updateEngraveQty = (moduleId, value, defaultQty) => {
    const numValue = parseInt(value) || 0;
    setEngraveQuantities(prev => ({
      ...prev,
      [moduleId]: numValue
    }));
    
    // Auto-select if quantity > 0, auto-deselect if 0
    const newSelected = new Set(selectedModules);
    if (numValue > 0) {
      newSelected.add(moduleId);
    } else {
      newSelected.delete(moduleId);
    }
    setSelectedModules(newSelected);
  };

  // Calculate totals
  const totalModulesSelected = selectedModules.size;
  const totalQtySelected = useMemo(() => {
    let total = 0;
    Object.values(hierarchicalData).forEach(baseType => {
      Object.values(baseType.orders).forEach(order => {
        order.modules.forEach(m => {
          if (selectedModules.has(m.id)) {
            total += getEngraveQty(m.id, m.qtyNeeded);
          }
        });
      });
    });
    return total;
  }, [selectedModules, engraveQuantities, hierarchicalData]);

  // Clear selection
  const clearSelection = () => {
    setSelectedModules(new Set());
    setEngraveQuantities({});
  };

  // Render checkbox based on state
  const renderCheckbox = (state, onClick, size = 'normal') => {
    const sizeClass = size === 'small' ? 'w-4 h-4' : 'w-5 h-5';
    
    return (
      <button
        onClick={(e) => {
          e.stopPropagation();
          onClick();
        }}
        className={`${sizeClass} rounded flex items-center justify-center transition-all`}
        style={{
          backgroundColor: state === 'none' ? colors.bgCard : colors.wpBlue,
          border: state === 'none' ? `2px solid ${colors.border}` : `2px solid ${colors.wpBlue}`
        }}
      >
        {state === 'all' && <Check className="w-3 h-3 text-white" strokeWidth={3} />}
        {state === 'partial' && <Minus className="w-3 h-3 text-white" strokeWidth={3} />}
      </button>
    );
  };

  return (
    <div 
      className="min-h-screen p-6"
      style={{ 
        fontFamily: "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif",
        backgroundColor: colors.bgPrimary,
        color: colors.textPrimary
      }}
    >
      <div className="max-w-4xl mx-auto">
        {/* WordPress-style Header */}
        <div 
          className="rounded-t px-6 py-4 flex items-center justify-between"
          style={{ 
            backgroundColor: colors.bgCard,
            borderBottom: `1px solid ${colors.borderLight}`
          }}
        >
          <div className="flex items-center gap-3">
            <div 
              className="w-10 h-10 rounded flex items-center justify-center"
              style={{ backgroundColor: colors.wpBlue }}
            >
              <Zap className="w-6 h-6 text-white" />
            </div>
            <div>
              <h1 className="text-xl font-semibold" style={{ color: colors.textPrimary }}>
                Module Engraving Batch Creator
              </h1>
              <p className="text-sm" style={{ color: colors.textMuted }}>
                Select modules to include in engraving batch
              </p>
            </div>
          </div>
          <button
            onClick={() => {
              // TODO: Navigate to Batch History page
              console.log('Navigate to Batch History');
            }}
            className="flex items-center gap-1 text-sm px-3 py-2 rounded transition-all"
            style={{ 
              backgroundColor: colors.bgElevated,
              color: colors.wpBlue,
              border: `1px solid ${colors.border}`
            }}
            onMouseOver={(e) => {
              e.currentTarget.style.backgroundColor = colors.wpBlueLight;
              e.currentTarget.style.borderColor = colors.wpBlue;
            }}
            onMouseOut={(e) => {
              e.currentTarget.style.backgroundColor = colors.bgElevated;
              e.currentTarget.style.borderColor = colors.border;
            }}
            title="View previously completed batches for re-engraving"
          >
            <Clock className="w-4 h-4" />
            View Batch History
          </button>
        </div>

        {/* Stats Bar */}
        <div 
          className="grid grid-cols-3"
          style={{ 
            backgroundColor: colors.bgCard,
            borderLeft: `1px solid ${colors.borderLight}`,
            borderRight: `1px solid ${colors.borderLight}`
          }}
        >
          <div 
            className="p-4 text-center"
            style={{ borderRight: `1px solid ${colors.borderLight}` }}
          >
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Base Types</div>
            <div className="text-2xl font-semibold" style={{ color: colors.textPrimary }}>{Object.keys(hierarchicalData).length}</div>
          </div>
          <div 
            className="p-4 text-center"
            style={{ borderRight: `1px solid ${colors.borderLight}` }}
          >
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Selected SKUs</div>
            <div className="text-2xl font-semibold" style={{ color: colors.wpBlue }}>{totalModulesSelected}</div>
          </div>
          <div className="p-4 text-center">
            <div className="text-xs uppercase tracking-wider mb-1" style={{ color: colors.textMuted }}>Total Units</div>
            <div className="text-2xl font-semibold" style={{ color: colors.success }}>{totalQtySelected}</div>
          </div>
        </div>

        {/* Hierarchical List */}
        <div 
          className="rounded-b overflow-hidden"
          style={{ 
            border: `1px solid ${colors.borderLight}`,
            borderTop: 'none'
          }}
        >
          {/* Fixed Header Row */}
          <div 
            style={{ 
              backgroundColor: colors.bgElevated,
              borderBottom: `1px solid ${colors.borderLight}`,
              height: '48px'
            }}
          >
            {totalModulesSelected > 0 ? (
              <div className="px-4 h-full flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <span className="font-medium" style={{ color: colors.textPrimary }}>
                    {totalModulesSelected} module{totalModulesSelected !== 1 ? 's' : ''} selected
                  </span>
                  <span style={{ color: colors.textMuted }}>
                    ({totalQtySelected} units)
                  </span>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={clearSelection}
                    className="px-3 py-1.5 rounded text-sm transition-all"
                    style={{ 
                      backgroundColor: colors.bgCard,
                      color: colors.textSecondary,
                      border: `1px solid ${colors.border}`
                    }}
                    onMouseOver={(e) => {
                      e.currentTarget.style.borderColor = colors.error;
                      e.currentTarget.style.color = colors.error;
                    }}
                    onMouseOut={(e) => {
                      e.currentTarget.style.borderColor = colors.border;
                      e.currentTarget.style.color = colors.textSecondary;
                    }}
                  >
                    Clear Selection
                  </button>
                  <button
                    onClick={() => console.log('Start engraving', [...selectedModules])}
                    className="px-4 py-1.5 rounded text-sm font-medium transition-all flex items-center gap-2"
                    style={{ 
                      backgroundColor: colors.wpBlue,
                      color: '#FFFFFF'
                    }}
                    onMouseOver={(e) => {
                      e.currentTarget.style.backgroundColor = colors.wpBlueHover;
                    }}
                    onMouseOut={(e) => {
                      e.currentTarget.style.backgroundColor = colors.wpBlue;
                    }}
                  >
                    <Zap className="w-4 h-4" />
                    Start Engraving
                  </button>
                </div>
              </div>
            ) : (
              <div 
                className="px-4 h-full flex items-center gap-2"
              >
                <Layers className="w-4 h-4" style={{ color: colors.wpBlue }} />
                <span 
                  className="text-sm font-medium flex-1"
                  style={{ color: colors.textSecondary }}
                >
                  Modules Awaiting Engraving
                </span>
                <button
                  onClick={() => {
                    // TODO: Implement WordPress DB query to refresh module list
                    console.log('Refresh module list');
                  }}
                  className="p-2 rounded transition-all"
                  style={{ color: colors.textMuted }}
                  onMouseOver={(e) => {
                    e.currentTarget.style.backgroundColor = colors.bgHover;
                    e.currentTarget.style.color = colors.wpBlue;
                  }}
                  onMouseOut={(e) => {
                    e.currentTarget.style.backgroundColor = 'transparent';
                    e.currentTarget.style.color = colors.textMuted;
                  }}
                  title="Refresh module list"
                >
                  <RefreshCw className="w-4 h-4" />
                </button>
              </div>
            )}
          </div>

          {/* Base Types */}
          <div style={{ backgroundColor: colors.bgCard }}>
            {Object.entries(hierarchicalData).map(([baseType, data]) => {
              const isExpanded = expandedBaseTypes.has(baseType);
              const selectionState = getBaseTypeSelectionState(baseType);
              const totalOrders = Object.keys(data.orders).length;
              const totalModules = Object.values(data.orders).reduce(
                (sum, order) => sum + order.modules.length, 0
              );
              
              return (
                <div 
                  key={baseType}
                  style={{ borderBottom: `1px solid ${colors.borderLight}` }}
                >
                  {/* Base Type Row */}
                  <div
                    className="px-4 py-3 flex items-center gap-3 cursor-pointer transition-all"
                    style={{ 
                      backgroundColor: isExpanded ? colors.bgHover : colors.bgCard
                    }}
                    onClick={() => toggleBaseType(baseType)}
                    onMouseOver={(e) => {
                      if (!isExpanded) e.currentTarget.style.backgroundColor = colors.bgElevated;
                    }}
                    onMouseOut={(e) => {
                      if (!isExpanded) e.currentTarget.style.backgroundColor = colors.bgCard;
                    }}
                  >
                    {renderCheckbox(selectionState, () => selectBaseType(baseType))}
                    
                    <div className="w-5 flex items-center justify-center">
                      {isExpanded ? (
                        <ChevronDown className="w-5 h-5" style={{ color: colors.textMuted }} />
                      ) : (
                        <ChevronRight className="w-5 h-5" style={{ color: colors.textMuted }} />
                      )}
                    </div>
                    
                    <span 
                      className="font-bold text-base px-2 py-0.5 rounded"
                      style={{ 
                        fontFamily: "'SF Mono', 'Consolas', monospace",
                        backgroundColor: colors.wpBlueLight,
                        color: colors.wpBlue
                      }}
                    >
                      {baseType}
                    </span>
                    
                    <span style={{ color: colors.textSecondary }}>{data.name}</span>
                    
                    <div className="ml-auto flex items-center gap-4 text-sm">
                      <span style={{ color: colors.textMuted }}>
                        {totalOrders} order{totalOrders !== 1 ? 's' : ''}
                      </span>
                      <span style={{ color: colors.textMuted }}>
                        {totalModules} module{totalModules !== 1 ? 's' : ''}
                      </span>
                    </div>
                  </div>

                  {/* Orders */}
                  {isExpanded && Object.entries(data.orders).map(([orderId, orderData]) => {
                    const orderKey = `${baseType}-${orderId}`;
                    const isOrderExpanded = expandedOrders.has(orderKey);
                    const orderSelectionState = getOrderSelectionState(baseType, orderId);
                    
                    return (
                      <div key={orderId}>
                        {/* Order Row */}
                        <div
                          className="px-4 py-2.5 flex items-center gap-3 cursor-pointer transition-all"
                          style={{ 
                            paddingLeft: '48px',
                            backgroundColor: isOrderExpanded ? colors.bgElevated : colors.bgCard,
                            borderTop: `1px solid ${colors.borderLight}`
                          }}
                          onClick={() => toggleOrder(orderKey)}
                          onMouseOver={(e) => {
                            if (!isOrderExpanded) e.currentTarget.style.backgroundColor = colors.bgElevated;
                          }}
                          onMouseOut={(e) => {
                            if (!isOrderExpanded) e.currentTarget.style.backgroundColor = colors.bgCard;
                          }}
                        >
                          {renderCheckbox(orderSelectionState, () => toggleOrderSelection(baseType, orderId), 'small')}
                          
                          <div className="w-4 flex items-center justify-center">
                            {isOrderExpanded ? (
                              <ChevronDown className="w-4 h-4" style={{ color: colors.textMuted }} />
                            ) : (
                              <ChevronRight className="w-4 h-4" style={{ color: colors.textMuted }} />
                            )}
                          </div>
                          
                          <Package className="w-4 h-4" style={{ color: colors.textMuted }} />
                          
                          <span 
                            className="font-medium"
                            style={{ color: colors.wpBlue }}
                          >
                            #{orderId}
                          </span>
                          
                          <span style={{ color: colors.textSecondary }}>{orderData.customer}</span>
                          
                          <span 
                            className="ml-auto text-sm px-2 py-0.5 rounded"
                            style={{ 
                              backgroundColor: colors.bgElevated,
                              color: colors.textMuted
                            }}
                          >
                            {orderData.modules.length} module{orderData.modules.length !== 1 ? 's' : ''}
                          </span>
                        </div>

                        {/* Modules */}
                        {isOrderExpanded && orderData.modules.map(module => {
                          const isSelected = selectedModules.has(module.id);
                          const engraveQty = getEngraveQty(module.id, module.qtyNeeded);
                          
                          return (
                            <div
                              key={module.id}
                              className="px-4 py-2 flex items-center gap-3 transition-all"
                              style={{ 
                                paddingLeft: '80px',
                                backgroundColor: isSelected ? colors.successLight : colors.bgCard,
                                borderTop: `1px solid ${colors.borderLight}`
                              }}
                              onMouseOver={(e) => {
                                if (!isSelected) e.currentTarget.style.backgroundColor = colors.bgElevated;
                              }}
                              onMouseOut={(e) => {
                                if (!isSelected) e.currentTarget.style.backgroundColor = isSelected ? colors.successLight : colors.bgCard;
                              }}
                            >
                              {renderCheckbox(
                                isSelected ? 'all' : 'none',
                                () => toggleModuleSelection(module.id),
                                'small'
                              )}
                              
                              <Hash className="w-3 h-3" style={{ color: colors.textMuted }} />
                              
                              <span 
                                className="font-medium text-sm"
                                style={{ 
                                  fontFamily: "'SF Mono', 'Consolas', monospace",
                                  color: colors.textPrimary
                                }}
                              >
                                {module.sku}
                              </span>
                              
                              <div className="ml-auto flex items-center gap-3">
                                <span 
                                  className="text-sm"
                                  style={{ color: colors.textMuted }}
                                >
                                  {module.qtyReceived}/{module.buildQty} complete
                                </span>
                                
                                <div className="flex items-center gap-1">
                                  <input
                                    type="number"
                                    min="0"
                                    value={engraveQty}
                                    onChange={(e) => updateEngraveQty(module.id, e.target.value, module.qtyNeeded)}
                                    onClick={(e) => e.stopPropagation()}
                                    className="w-14 px-2 py-1 rounded text-center text-sm font-medium"
                                    style={{
                                      backgroundColor: colors.bgCard,
                                      color: colors.textPrimary,
                                      border: `1px solid ${colors.border}`,
                                      outline: 'none'
                                    }}
                                    onFocus={(e) => {
                                      e.target.style.borderColor = colors.wpBlue;
                                      e.target.style.boxShadow = `0 0 0 1px ${colors.wpBlue}`;
                                    }}
                                    onBlur={(e) => {
                                      e.target.style.borderColor = colors.border;
                                      e.target.style.boxShadow = 'none';
                                    }}
                                  />
                                  <span 
                                    className="text-sm"
                                    style={{ color: colors.textMuted }}
                                  >
                                    / {module.qtyNeeded}
                                  </span>
                                </div>
                              </div>
                            </div>
                          );
                        })}
                      </div>
                    );
                  })}
                </div>
              );
            })}
          </div>
        </div>

        {/* Footer */}
        <div 
          className="mt-4 text-center text-xs py-2"
          style={{ color: colors.textMuted }}
        >
          QSA Engraving System • Quadica Developments Inc.
        </div>
      </div>
    </div>
  );
}
